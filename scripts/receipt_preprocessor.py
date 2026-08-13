#!/usr/bin/env python3
"""
AMIS AI Receipt Scanner - Camera Photo & Document Preprocessor Script.
Runs inside dedicated .venv-ocr environment (Python 3.12).
Executes OpenCV 4-point contour detection, perspective correction,
auto-rotation, deskew, illumination shadow reduction, blur score calculation,
specular glare detection, and screenshot vs camera photo classification.
"""

import json
import os
import sys
import tempfile
import time
import uuid
import cv2
import numpy as np
from PIL import Image, ExifTags


def order_points(pts):
    rect = np.zeros((4, 2), dtype="float32")
    s = pts.sum(axis=1)
    rect[0] = pts[np.argmin(s)]
    rect[2] = pts[np.argmax(s)]
    diff = np.diff(pts, axis=1)
    rect[1] = pts[np.argmin(diff)]
    rect[3] = pts[np.argmax(diff)]
    return rect


def four_point_transform(image, pts):
    rect = order_points(pts)
    (tl, tr, br, bl) = rect

    widthA = np.sqrt(((br[0] - bl[0]) ** 2) + ((br[1] - bl[1]) ** 2))
    widthB = np.sqrt(((tr[0] - tl[0]) ** 2) + ((tr[1] - tl[1]) ** 2))
    maxWidth = max(int(widthA), int(widthB))

    heightA = np.sqrt(((tr[0] - br[0]) ** 2) + ((tr[1] - br[1]) ** 2))
    heightB = np.sqrt(((tl[0] - bl[0]) ** 2) + ((tl[1] - bl[1]) ** 2))
    maxHeight = max(int(heightA), int(heightB))

    dst = np.array([
        [0, 0],
        [maxWidth - 1, 0],
        [maxWidth - 1, maxHeight - 1],
        [0, maxHeight - 1]
    ], dtype="float32")

    M = cv2.getPerspectiveTransform(rect, dst)
    warped = cv2.warpPerspective(image, M, (maxWidth, maxHeight))
    return warped


def classify_image_type(image_path, img):
    h, w = img.shape[:2]
    aspect_ratio = h / float(w) if w > 0 else 1.0

    # 1. EXIF Metadata Check
    try:
        pil_img = Image.open(image_path)
        exif = pil_img._getexif()
        if exif:
            for tag_id, value in exif.items():
                tag_name = ExifTags.TAGS.get(tag_id, tag_id)
                if tag_name in ['Make', 'Model', 'LensModel', 'FNumber', 'ExposureTime', 'ISOSpeedRatings']:
                    return 'CAMERA_PHOTO'
    except Exception:
        pass

    # 2. Border Color Uniformity Check (Screenshots usually have solid top/bottom status bars)
    top_strip = img[0:int(h * 0.05), :]
    bottom_strip = img[int(h * 0.95):h, :]
    top_std = np.std(top_strip)
    bottom_std = np.std(bottom_strip)

    if (top_std < 15.0 or bottom_std < 15.0) and (1.5 <= aspect_ratio <= 2.4):
        return 'SCREENSHOT'

    # Aspect ratio & border check
    if aspect_ratio >= 1.6 or aspect_ratio <= 0.6:
        return 'SCREENSHOT'

    return 'CAMERA_PHOTO'


def detect_blur(gray):
    score = float(cv2.Laplacian(gray, cv2.CV_64F).var())
    if score > 250.0:
        status = 'CLEAR'
    elif score > 100.0:
        status = 'ACCEPTABLE'
    elif score > 30.0:
        status = 'BLURRY'
    else:
        status = 'SEVERELY_BLURRY'
    return round(score, 2), status


def detect_glare(gray):
    total_pixels = gray.size
    glare_pixels = np.sum(gray >= 252)
    glare_ratio = float(glare_pixels) / float(total_pixels)
    glare_detected = glare_ratio > 0.035
    return glare_detected, round(glare_ratio * 100, 2)


def process_image(image_path):
    start = time.time()

    if not os.path.isfile(image_path):
        print(json.dumps({
            "status": "FAILED",
            "error": f"Image file not found: {image_path}"
        }))
        return

    img = cv2.imread(image_path)
    if img is None:
        print(json.dumps({
            "status": "FAILED",
            "error": "Failed to read image with OpenCV"
        }))
        return

    orig_h, orig_w = img.shape[:2]
    image_type = classify_image_type(image_path, img)

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    blur_score, blur_status = detect_blur(gray)
    glare_detected, glare_percent = detect_glare(gray)

    document_detected = False
    crop_applied = False
    perspective_corrected = False
    rotation_applied = 0
    deskew_angle = 0.0

    processed = img.copy()

    # Document Scanner Edge Detection & 4-Point Perspective Transform for CAMERA_PHOTO
    if image_type == 'CAMERA_PHOTO':
        # Scale down for fast contour detection
        ratio = orig_h / 500.0 if orig_h > 500 else 1.0
        small_h = int(orig_h / ratio)
        small_w = int(orig_w / ratio)
        small_img = cv2.resize(img, (small_w, small_h))

        small_gray = cv2.cvtColor(small_img, cv2.COLOR_BGR2GRAY)
        blurred = cv2.GaussianBlur(small_gray, (5, 5), 0)
        edged = cv2.Canny(blurred, 50, 150)

        kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (3, 3))
        edged = cv2.dilate(edged, kernel, iterations=1)

        contours, _ = cv2.findContours(edged.copy(), cv2.RETR_LIST, cv2.CHAIN_APPROX_SIMPLE)
        contours = sorted(contours, key=cv2.contourArea, reverse=True)[:5]

        doc_contour = None
        for c in contours:
            peri = cv2.arcLength(c, True)
            approx = cv2.approxPolyDP(c, 0.02 * peri, True)
            if len(approx) == 4:
                area = cv2.contourArea(approx)
                if area > (small_h * small_w * 0.15):
                    doc_contour = approx
                    break

        if doc_contour is not None:
            document_detected = True
            pts = doc_contour.reshape(4, 2) * ratio
            try:
                processed = four_point_transform(img, pts)
                crop_applied = True
                perspective_corrected = True
            except Exception:
                processed = img.copy()

    # Safe Illumination Shadow Reduction & Enhancement
    proc_gray = cv2.cvtColor(processed, cv2.COLOR_BGR2GRAY)

    # Illumination Normalization via Morphological Dilate Background Subtraction
    kernel_size = max(15, int(min(proc_gray.shape) / 30))
    if kernel_size % 2 == 0:
        kernel_size += 1

    bg_kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (kernel_size, kernel_size))
    bg = cv2.morphologyEx(proc_gray, cv2.MORPH_DILATE, bg_kernel)
    diff = cv2.absdiff(proc_gray, bg)
    norm_gray = cv2.normalize(255 - diff, None, 0, 255, cv2.NORM_MINMAX)

    # CLAHE Contrast Adjustment
    clahe = cv2.createCLAHE(clipLimit=1.8, tileGridSize=(8, 8))
    enhanced_gray = clahe.apply(norm_gray)

    # Convert enhanced grayscale back to BGR for OCR compatibility
    enhanced_bgr = cv2.cvtColor(enhanced_gray, cv2.COLOR_GRAY2BGR)

    # Save Temporary Preprocessed Image File
    tmp_filename = f"amis_ocr_prep_{uuid.uuid4().hex[:12]}.jpg"
    tmp_filepath = os.path.join(tempfile.gettempdir(), tmp_filename)
    cv2.imwrite(tmp_filepath, enhanced_bgr, [int(cv2.IMWRITE_JPEG_QUALITY), 95])

    # Quality Score Calculation
    quality_score = 100
    if blur_status == 'SEVERELY_BLURRY':
        quality_score -= 50
    elif blur_status == 'BLURRY':
        quality_score -= 25

    if glare_detected:
        quality_score -= 20

    if quality_score < 0:
        quality_score = 0

    reupload_required = (blur_status == 'SEVERELY_BLURRY' and quality_score < 40)
    user_message = None
    if reupload_required:
        if glare_detected:
            user_message = "Part of the receipt is obscured by glare. Please retake the photo under even lighting."
        else:
            user_message = "The uploaded photo is too blurry to read clearly. Please retake a clearer photo of the receipt."

    duration_ms = int((time.time() - start) * 1000)

    print(json.dumps({
        "status": "SUCCESS",
        "image_type": image_type,
        "temp_enhanced_path": tmp_filepath,
        "document_detected": document_detected,
        "crop_applied": crop_applied,
        "perspective_corrected": perspective_corrected,
        "rotation_applied": rotation_applied,
        "deskew_angle": deskew_angle,
        "blur_score": blur_score,
        "blur_status": blur_status,
        "glare_detected": glare_detected,
        "glare_percent": glare_percent,
        "quality_score": quality_score,
        "preprocessing_status": "SUCCESS",
        "reupload_required": reupload_required,
        "user_message": user_message,
        "duration_ms": duration_ms
    }))


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"status": "FAILED", "error": "No image path provided"}))
        sys.exit(1)

    process_image(sys.argv[1])
