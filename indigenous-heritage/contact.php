<?php
require_once __DIR__ . '/includes/bootstrap.php';

$errors = [];
$sent = isset($_GET['sent']);

if (is_post()) {
    $name = post_str('name', 100);
    $email = post_str('email', 100);
    $subject = post_str('subject', 200);
    $message = post_str('message', 5000);

    // Bots tend to fill every field, including this hidden one.
    if (post_str('website') !== '') {
        redirect('contact.php', ['sent' => 1]);
    }
    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        $errors[] = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (mb_strlen($message) < 10) {
        $errors[] = 'Please write a slightly longer message (at least 10 characters).';
    } else {
        $recent = (int) db_value('SELECT COUNT(*) FROM messages WHERE email = ? AND created_at > ?', [$email, date('Y-m-d H:i:s', time() - 3600)]);
        if ($recent >= 5) {
            $errors[] = 'You have sent several messages recently. Please wait a while before sending another.';
        } else {
            db_run('INSERT INTO messages (name, email, subject, message) VALUES (?, ?, ?, ?)', [$name, $email, $subject, $message]);
            redirect('contact.php', ['sent' => 1]);
        }
    }
}

$page_title = 'Contact & Contributions';
$page_description = 'Contact the archive team, propose a heritage record, or request a research collaboration.';
$active_nav = 'contact';
require __DIR__ . '/includes/partials/public_header.php';
?>
    <section class="page-hero">
        <div class="hero-content">
            <div class="hero-badge">❖ Connect With Cultural Custodians ❖</div>
            <h1>Contact &amp; Contributions</h1>
            <p>Reach the documentation team, propose a heritage record, request a correction, or ask about research and educational collaboration.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="contact-split-grid">
                <div>
                    <span class="section-badge">Get In Touch</span>
                    <h2 class="about-heading">Let's Preserve Heritage Together</h2>
                    <p>Do you have a question about a record, or would you like to contribute documentation from your community? Every contribution is reviewed with community custodians before publication.</p>

                    <div class="contact-cards-stack">
                        <div class="contact-card-box">
                            <div class="contact-icon-bubble">📍</div>
                            <div>
                                <h4>Archive Office</h4>
                                <p>Cordillera &amp; Southern Indigenous Regions, Philippines</p>
                            </div>
                        </div>
                        <div class="contact-card-box">
                            <div class="contact-icon-bubble">✉️</div>
                            <div>
                                <h4>Email</h4>
                                <p><?php echo e(app_config('contact_email')); ?></p>
                            </div>
                        </div>
                        <div class="contact-card-box">
                            <div class="contact-icon-bubble">🕘</div>
                            <div>
                                <h4>Office Hours</h4>
                                <p>Monday – Friday: 9:00 AM – 5:00 PM (PHT)</p>
                            </div>
                        </div>
                    </div>

                    <div class="faq-wrap">
                        <h3>Frequently Asked Questions</h3>
                        <div class="faq-item active">
                            <button type="button" class="faq-question"><span>How can I contribute a record?</span><span class="faq-chevron">▼</span></button>
                            <div class="faq-answer">Send us a message describing the tradition, story, or item, who holds the knowledge, and whether your community consents to sharing it. Staff will prepare a draft for community review.</div>
                        </div>
                        <div class="faq-item">
                            <button type="button" class="faq-question"><span>How is sensitive knowledge protected?</span><span class="faq-chevron">▼</span></button>
                            <div class="faq-answer">Records can be Public, Restricted (approved community members), or Private (staff only). Sacred material is never published publicly, and sensitive locations stay hidden.</div>
                        </div>
                        <div class="faq-item">
                            <button type="button" class="faq-question"><span>Can my community withdraw a record?</span><span class="faq-chevron">▼</span></button>
                            <div class="faq-answer">Yes. Contact us, and we will mark consent as withdrawn, which immediately removes the record from public view.</div>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <h3 class="form-card-title">Send Us a Message</h3>
                    <p class="form-card-intro">Our documentation coordinator will respond within 2 business days.</p>

                    <?php render_flashes(); ?>
                    <?php if ($sent): ?>
                        <div class="form-alert form-alert-success" role="status"><span>✓</span><span>Thank you! Your message has been received. Our team will review it shortly.</span></div>
                    <?php endif; ?>
                    <?php foreach ($errors as $error): ?>
                        <div class="form-alert form-alert-danger" role="alert"><span>⚠</span><span><?php echo e($error); ?></span></div>
                    <?php endforeach; ?>

                    <form method="POST" action="<?php echo e(url('contact.php')); ?>" novalidate>
                        <?php echo csrf_field(); ?>
                        <div class="hp-field" aria-hidden="true">
                            <label for="website">Leave this field empty</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <?php
                        render_form_fields([
                            ['name' => 'name', 'label' => 'Your Full Name', 'placeholder' => 'e.g. Maria Santos', 'required' => true, 'maxlength' => 100, 'autocomplete' => 'name'],
                            ['name' => 'email', 'type' => 'email', 'label' => 'Email Address', 'placeholder' => 'e.g. maria@example.com', 'required' => true, 'maxlength' => 100, 'autocomplete' => 'email'],
                            ['name' => 'subject', 'label' => 'Subject', 'placeholder' => 'e.g. Record contribution / Research inquiry', 'required' => true, 'maxlength' => 200],
                            ['name' => 'message', 'type' => 'textarea', 'label' => 'Message', 'placeholder' => 'Describe your inquiry, community, or proposed record…', 'required' => true, 'maxlength' => 5000],
                        ], $sent ? [] : null);
                        ?>
                        <button type="submit" class="btn btn-primary btn-lg btn-block">Send Message ✉️</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/includes/partials/public_footer.php'; ?>
