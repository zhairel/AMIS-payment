#!/usr/bin/env python3
"""Conservative OpenCV receipt cleanup. Never writes to the source image."""

import json
import sys

import cv2
import numpy as np


def estimated_skew(gray):
    inverted = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)[1]
    points = np.column_stack(np.where(inverted > 0))
    if len(points) < 100:
        return 0.0
    angle = cv2.minAreaRect(points)[-1]
    angle = -(90 + angle) if angle < -45 else -angle
    return float(angle) if abs(angle) <= 10 else 0.0


def rotate(image, angle):
    if abs(angle) < 0.3:
        return image
    height, width = image.shape[:2]
    matrix = cv2.getRotationMatrix2D((width / 2, height / 2), angle, 1.0)
    return cv2.warpAffine(image, matrix, (width, height), flags=cv2.INTER_CUBIC,
                          borderMode=cv2.BORDER_CONSTANT, borderValue=(255, 255, 255))


def main():
    source, target = sys.argv[1], sys.argv[2]
    image = cv2.imread(source, cv2.IMREAD_COLOR)
    if image is None:
        raise RuntimeError("Source receipt could not be decoded")

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    angle = estimated_skew(gray)
    image = rotate(image, angle)
    height, width = image.shape[:2]
    scale = min(2.0, max(1.0, 1400.0 / max(1, min(height, width))))
    if scale > 1.05:
        image = cv2.resize(image, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    clahe = cv2.createCLAHE(clipLimit=1.8, tileGridSize=(8, 8))
    enhanced = clahe.apply(gray)
    denoised = cv2.fastNlMeansDenoising(enhanced, None, 5, 7, 21)
    softened = cv2.GaussianBlur(denoised, (0, 0), 1.0)
    sharpened = cv2.addWeighted(denoised, 1.25, softened, -0.25, 0)
    if not cv2.imwrite(target, sharpened, [cv2.IMWRITE_PNG_COMPRESSION, 3]):
        raise RuntimeError("Processed receipt could not be written")

    print(json.dumps({"success": True, "rotation": round(angle, 2), "scale": round(scale, 2)}))


if __name__ == "__main__":
    main()
