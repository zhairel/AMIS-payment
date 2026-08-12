<footer class="amis-site-footer" x-data="{ activeFooterModal: null }">
    <div class="amis-footer-container">
        <nav class="amis-footer-links" aria-label="Footer Navigation">
            <button type="button" @click="activeFooterModal = 'privacy'" class="amis-footer-link">Privacy Notice</button>
            <span class="amis-footer-bullet" aria-hidden="true">•</span>
            <button type="button" @click="activeFooterModal = 'terms'" class="amis-footer-link">Terms of Use</button>
            <span class="amis-footer-bullet" aria-hidden="true">•</span>
            <button type="button" @click="activeFooterModal = 'security'" class="amis-footer-link">Data Security</button>
            <span class="amis-footer-bullet" aria-hidden="true">•</span>
            <button type="button" @click="activeFooterModal = 'support'" class="amis-footer-link">Contact Support</button>
        </nav>

        <p class="amis-footer-subtext">For authorized school use only.</p>

        <p class="amis-footer-copyright">&copy; 2026 Al Munawwara Islamic School. All rights reserved.</p>
    </div>

    {{-- Privacy Notice Modal --}}
    <div x-show="activeFooterModal === 'privacy'" x-cloak class="amis-footer-modal-backdrop" @click.self="activeFooterModal = null" @keydown.escape.window="activeFooterModal = null">
        <div class="amis-footer-modal-card is-large" role="dialog" aria-modal="true" aria-labelledby="footer-privacy-title">
            <div class="amis-footer-modal-header">
                <h3 id="footer-privacy-title">Privacy Notice</h3>
                <button type="button" @click="activeFooterModal = null" class="amis-footer-modal-close" aria-label="Close modal">&times;</button>
            </div>
            <div class="amis-footer-modal-body">
                <div class="amis-policy-section">
                    <h4>Personal Information Collected</h4>
                    <p>AMIS collects and processes personal information necessary for school administration, enrollment, and payment services, including:</p>
                    <ul class="amis-privacy-list">
                        <li>Student and parent/guardian identification and contact details</li>
                        <li>Enrollment records and academic classification information</li>
                        <li>Payment and transaction details (reference numbers, dates, amounts)</li>
                        <li>Uploaded proof of payment, bank transfer confirmations, or transaction receipts</li>
                    </ul>
                </div>

                <div class="amis-policy-section">
                    <h4>Purpose of Processing</h4>
                    <p>Information is processed solely for legitimate school operations, student enrollment confirmation, fee collection management, and payment verification.</p>
                </div>

                <div class="amis-policy-section">
                    <h4>Access to Information</h4>
                    <p>Access is restricted strictly to authorized school administrators, finance staff, and system personnel responsible for student accounts.</p>
                </div>

                <div class="amis-policy-section">
                    <h4>Data Subject Rights & Privacy Concerns</h4>
                    <p>In accordance with Philippine privacy regulations, users have the right to be informed, request access, correct inaccurate records, and object to unauthorized processing. For any privacy inquiries, please contact AMIS Support at <a href="mailto:amisonlinesupport@gmail.com" class="amis-link-accent">amisonlinesupport@gmail.com</a>.</p>
                </div>

                <div class="amis-policy-section is-references">
                    <h4>Official Privacy References</h4>
                    <ul class="amis-references-list">
                        <li><a href="https://privacy.gov.ph/data-privacy-act/" target="_blank" rel="noopener noreferrer">Data Privacy Act of 2012 (Republic Act No. 10173)</a></li>
                        <li><a href="https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/" target="_blank" rel="noopener noreferrer">Implementing Rules and Regulations of the Data Privacy Act</a></li>
                        <li><a href="https://privacy.gov.ph/data-subject-rights/" target="_blank" rel="noopener noreferrer">Data Subject Rights</a></li>
                        <li><a href="https://privacy.gov.ph/" target="_blank" rel="noopener noreferrer">National Privacy Commission</a></li>
                        <li><a href="https://privacy.gov.ph/day-to-day/" target="_blank" rel="noopener noreferrer">NPC Privacy Guidance</a></li>
                    </ul>
                </div>
            </div>
            <div class="amis-footer-modal-actions">
                <button type="button" @click="activeFooterModal = null" class="amis-footer-btn-close">Close</button>
            </div>
        </div>
    </div>

    {{-- Terms of Use Modal --}}
    <div x-show="activeFooterModal === 'terms'" x-cloak class="amis-footer-modal-backdrop" @click.self="activeFooterModal = null" @keydown.escape.window="activeFooterModal = null">
        <div class="amis-footer-modal-card" role="dialog" aria-modal="true" aria-labelledby="footer-terms-title">
            <div class="amis-footer-modal-header">
                <h3 id="footer-terms-title">Terms of Use</h3>
                <button type="button" @click="activeFooterModal = null" class="amis-footer-modal-close" aria-label="Close modal">&times;</button>
            </div>
            <div class="amis-footer-modal-body">
                <div class="amis-policy-section">
                    <ul class="amis-privacy-list">
                        <li><strong>Authorized Use:</strong> This portal is provided for official school services, enrollment management, and payment processing for enrolled students and their families.</li>
                        <li><strong>Account Responsibility:</strong> Users are responsible for maintaining the confidentiality of their login credentials and for all activities under their account.</li>
                        <li><strong>Accurate Submissions:</strong> Information provided and receipt images uploaded must be genuine, accurate, and belonging to legitimate transactions.</li>
                        <li><strong>Prohibited Misuse:</strong> Unauthorized access, submission of fraudulent receipts, or tampering with school records is strictly prohibited.</li>
                        <li><strong>Service Updates:</strong> School policies, fee schedules, and portal features may be updated when necessary to support school operations.</li>
                    </ul>
                </div>
            </div>
            <div class="amis-footer-modal-actions">
                <button type="button" @click="activeFooterModal = null" class="amis-footer-btn-close">Close</button>
            </div>
        </div>
    </div>

    {{-- Data Security Modal --}}
    <div x-show="activeFooterModal === 'security'" x-cloak class="amis-footer-modal-backdrop" @click.self="activeFooterModal = null" @keydown.escape.window="activeFooterModal = null">
        <div class="amis-footer-modal-card" role="dialog" aria-modal="true" aria-labelledby="footer-security-title">
            <div class="amis-footer-modal-header">
                <h3 id="footer-security-title">Data Security</h3>
                <button type="button" @click="activeFooterModal = null" class="amis-footer-modal-close" aria-label="Close modal">&times;</button>
            </div>
            <div class="amis-footer-modal-body">
                <p>AMIS applies appropriate administrative and technical measures to help protect student, parent, enrollment, and payment information. Users should keep their account credentials private and report suspicious account activity to AMIS Support.</p>
            </div>
            <div class="amis-footer-modal-actions">
                <button type="button" @click="activeFooterModal = null" class="amis-footer-btn-close">Close</button>
            </div>
        </div>
    </div>

    {{-- Contact Support Modal --}}
    <div x-show="activeFooterModal === 'support'" x-cloak class="amis-footer-modal-backdrop" @click.self="activeFooterModal = null" @keydown.escape.window="activeFooterModal = null">
        <div class="amis-footer-modal-card" role="dialog" aria-modal="true" aria-labelledby="footer-support-title">
            <div class="amis-footer-modal-header">
                <h3 id="footer-support-title">Contact Support</h3>
                <button type="button" @click="activeFooterModal = null" class="amis-footer-modal-close" aria-label="Close modal">&times;</button>
            </div>
            <div class="amis-footer-modal-body">
                <p>If you need assistance with your family payments or account, please reach out to AMIS Support:</p>
                <div class="amis-support-contact-info">
                    <strong>Email Support:</strong> <a href="mailto:amisonlinesupport@gmail.com">amisonlinesupport@gmail.com</a>
                </div>
            </div>
            <div class="amis-footer-modal-actions">
                <button type="button" @click="activeFooterModal = null" class="amis-footer-btn-close">Close</button>
            </div>
        </div>
    </div>
</footer>
