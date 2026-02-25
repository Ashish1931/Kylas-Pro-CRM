<?php get_header(); ?>

<main class="container" id="top">
    <section class="hero">
        <div class="hero-content">
            <h1>Scale Your Business with Smart Lead Tracking</h1>
            <p>Connect your high-converting forms directly to Kylas CRM. Real-time sync, custom mapping, and automated notifications for your sales team.</p>

            <div class="hero-subpoints">
                <p>✔ Zero-code integration with Contact Form 7</p>
                <p>✔ Automatic lead capture and enrichment</p>
                <p>✔ Reliable sync with retry and error logging</p>
            </div>
        </div>

        <div class="form-card" id="contact-form">
            <h2>Get In Touch</h2>

            <?php
            if ( shortcode_exists( 'contact-form-7' ) ) :
                $forms = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'posts_per_page' => 1 ) );
                if ( $forms ) :
                    $form_id = $forms[0]->ID;
            ?>

            <!-- Custom styled fields (visible to user) -->
            <div class="kylas-form-ui">

                <div class="name-row">
                    <label class="form-label">
                        First Name <span class="req">*</span>
                        <input type="text" id="kf-first-name" class="wpcf7-text" placeholder="First Name" required>
                    </label>
                    <label class="form-label">
                        Last Name <span class="req">*</span>
                        <input type="text" id="kf-last-name" class="wpcf7-text" placeholder="Last Name" required>
                    </label>
                </div>

                <label class="form-label">
                    Email <span class="req">*</span>
                    <input type="email" id="kf-email" class="wpcf7-email" placeholder="Email Address" required>
                </label>

                <label class="form-label">
                    Phone <span class="req">*</span>
                    <input type="tel" id="kf-phone" class="wpcf7-tel" placeholder="Phone Number" required>
                </label>

                <label class="form-label">
                    Message
                    <textarea id="kf-message" class="wpcf7-textarea" placeholder="Your Message (Optional)" rows="3"></textarea>
                </label>

                <button id="kf-submit" class="wpcf7-submit" type="button">Send Message ✉️</button>
                <button id="kf-retry" class="kylas-crm-retry" type="button" disabled>Retry</button>
                <div id="kf-response" style="margin-top:0.75rem; font-size:0.88rem; min-height:1.2em;"></div>
                <div id="kf-retry-status" class="kylas-crm-retry-status"></div>
            </div>

            <!-- Hidden CF7 form — does the actual processing -->
            <div style="display:none; visibility:hidden;" aria-hidden="true">
                <?php echo do_shortcode( '[contact-form-7 id="' . intval( $form_id ) . '"]' ); ?>
            </div>

            <script>
            (function(){
                var btn = document.getElementById('kf-submit');
                var res = document.getElementById('kf-response');
                if (!btn) return;

                btn.addEventListener('click', function(){
                    var fn  = document.getElementById('kf-first-name').value.trim();
                    var ln  = document.getElementById('kf-last-name').value.trim();
                    var em  = document.getElementById('kf-email').value.trim();
                    var ph  = document.getElementById('kf-phone').value.trim();
                    var msg = document.getElementById('kf-message').value.trim();

                    // Client-side validation
                    if (!fn || !ln || !em || !ph) {
                        res.innerHTML = '<span style="color:#ef4444;">⚠️ Please fill in all required fields.</span>';
                        return;
                    }
                    if (!/\S+@\S+\.\S+/.test(em)) {
                        res.innerHTML = '<span style="color:#ef4444;">⚠️ Please enter a valid email address.</span>';
                        return;
                    }

                    // Copy values into hidden CF7 form inputs
                    var hForm = document.querySelector('.wpcf7 form');
                    if (!hForm) {
                        res.innerHTML = '<span style="color:#ef4444;">Form initialisation error. Please refresh.</span>';
                        return;
                    }

                    function setField(names, value) {
                        if (!Array.isArray(names)) {
                            names = [names];
                        }
                        for (var i = 0; i < names.length; i++) {
                            var el = hForm.querySelector('[name="' + names[i] + '"]');
                            if (el) { 
                                el.value = value; 
                                return;
                            }
                        }
                    }
                    setField(['first-name', 'fname', 'firstName', 'first_name'], fn);
                    setField(['last-name', 'lname', 'lastName', 'last_name'],  ln);
                    setField(['your-email', 'email', 'email-address'], em);
                    setField(['phone', 'tel', 'mobile-number'], ph);
                    setField(['your-message', 'message', 'description', 'requirement'], msg);

                    btn.disabled = true;
                    btn.textContent = 'Sending…';
                    res.innerHTML   = '';

                    // Trigger CF7 hidden submit
                    var hiddenBtn = hForm.querySelector('[type="submit"]');
                    if (hiddenBtn) hiddenBtn.click();

                    // Listen for CF7 events
                    document.addEventListener('wpcf7mailsent', function onSent(e){
                        var apiResponse = e && e.detail && e.detail.apiResponse;
                        var kylas = apiResponse && apiResponse.kylas;

                        // If CF7 mail sent but Kylas failed, keep user input so Retry makes sense.
                        if (kylas && kylas.status && kylas.status !== 'success') {
                            res.innerHTML = '<span style="color:#f97316;">Your message was sent, but CRM sync failed. You can use Retry below.</span>';
                            btn.disabled = false;
                            btn.textContent = 'Send Message ✉️';
                            document.removeEventListener('wpcf7mailsent', onSent);
                            return;
                        }

                        // Kylas succeeded (or not enabled) – full success, now clear the form.
                        res.innerHTML = '<span style="color:#4ade80;"> Thank you! We will get back to you shortly.</span>';
                        ['kf-first-name','kf-last-name','kf-email','kf-phone','kf-message'].forEach(function(id){
                            var el = document.getElementById(id);
                            if (el) el.value = '';
                        });
                        btn.disabled = false;
                        btn.textContent = 'Send Message ✉️';
                        document.removeEventListener('wpcf7mailsent', onSent);
                    });

                    document.addEventListener('wpcf7invalid', function onInvalid(e){
                        res.innerHTML = '<span style="color:#ef4444;"> Please check your inputs and try again.</span>';
                        btn.disabled = false;
                        btn.textContent = 'Send Message ✉️';
                        document.removeEventListener('wpcf7invalid', onInvalid);
                    });

                    document.addEventListener('wpcf7mailfailed', function onFailed(e){
                        res.innerHTML = '<span style="color:#ef4444;"> Submission failed. Please try again later.</span>';
                        btn.disabled = false;
                        btn.textContent = 'Send Message ✉️';
                        document.removeEventListener('wpcf7mailfailed', onFailed);
                    });
                });
            })();
            </script>

            <?php
                else :
                    echo '<p style="color:#94a3b8;text-align:center;">Please create a Contact Form 7 form in the dashboard.</p>';
                endif;
            else :
                echo '<p style="color:#94a3b8;text-align:center;">Contact Form 7 plugin is not active.</p>';
            endif;
            ?>
        </div>
    </section>
</main>

<section id="features" class="features-section">
    <div class="container">
        <h2 class="section-title">Why teams choose Kylas CRM integration</h2>
        <p class="section-subtitle">
            Everything you need to move leads from your website into your sales pipeline <br>without manual effort.
            <br>
        </p>

        <div class="features-grid">
            <div class="feature-item">
                <span class="feature-icon">🚀</span>
                <strong>Fast Sync</strong>
                <p>Push every qualified enquiry into Kylas CRM in real time so your team can respond faster.</p>
            </div>
            <div class="feature-item">
                <span class="feature-icon">🛡️</span>
                <strong>Secure</strong>
                <p>API key based authentication, error logging, and safer retry flows keep your data consistent.</p>
            </div>
            <div class="feature-item">
                <span class="feature-icon">📊</span>
                <strong>Scalable</strong>
                <p>Built on top of WordPress & Kylas APIs to handle thousands of submissions per day.</p>
            </div>
            <div class="feature-item">
                <span class="feature-icon">⚙️</span>
                <strong>Flexible Mapping</strong>
                <p>Map any Contact Form 7 field into Kylas lead fields without writing custom code.</p>
            </div>
            <div class="feature-item">
                <span class="feature-icon">🔁</span>
                <strong>Smart Retry</strong>
                <p>Network or key issues? Fix the configuration and resend to Kylas without losing the lead.</p>
            </div>
            <div class="feature-item">
                <span class="feature-icon">📥</span>
                <strong>Local Lead Store</strong>
                <p>Keep a local copy of every form submission so nothing is lost even if the API is down.</p>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
