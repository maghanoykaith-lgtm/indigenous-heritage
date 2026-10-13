<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page_title = 'About & Cultural Protocols';
$page_description = 'The purpose of the Indigenous Heritage Management System and how it protects culturally sensitive knowledge.';
$active_nav = 'about';
require __DIR__ . '/includes/partials/public_header.php';
?>
    <section class="page-hero">
        <div class="hero-content">
            <div class="hero-badge">❖ Cultural Preservation Charter ❖</div>
            <h1>About the System</h1>
            <p>The Indigenous Heritage Management System organizes, preserves, and manages Indigenous cultural heritage: traditions, cultural practices, historical records, artifacts, languages, arts, and knowledge.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="about-hero-grid">
                <div>
                    <span class="section-badge">Ancestral Custodianship</span>
                    <h2 class="about-heading">Why Digital Heritage Preservation Matters</h2>
                    <p class="about-lead">Indigenous cultures embody generations of accumulated wisdom, ecological balance, and artistic expression. Communities have developed sustainable farming methods, weaving geometries, oral epics, and deep relationships with forests and rivers.</p>
                    <p class="about-lead">As modernization accelerates, this system gives community custodians, elders, artisans, and researchers a shared place to document their cultural heritage on their own terms, and to decide who may see it.</p>
                    <div class="button-row">
                        <a href="<?php echo e(url('contact.php')); ?>" class="btn btn-primary btn-lg">Collaborate With Us 🤝</a>
                        <a href="<?php echo e(url('records.php')); ?>" class="btn btn-secondary btn-lg">Browse Records</a>
                    </div>
                </div>

                <div class="about-highlight-box">
                    <h3>Preservation Goals</h3>
                    <ul class="about-goals">
                        <li><span>❖</span><div><strong>Accurate living documentation</strong><p>Recording practices, music, languages, and oral histories faithfully, with their sources.</p></div></li>
                        <li><span>❖</span><div><strong>Support for knowledge holders</strong><p>Crediting the elders, artisans, and communities who carry this knowledge.</p></div></li>
                        <li><span>❖</span><div><strong>Intergenerational transfer</strong><p>Helping younger generations access and continue their heritage.</p></div></li>
                        <li><span>❖</span><div><strong>Ecological harmony</strong><p>Highlighting sustainable, respectful use of natural materials.</p></div></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="section section-alt">
        <div class="container">
            <div class="section-title-wrap">
                <span class="section-badge">Cultural Privacy &amp; Consent</span>
                <h2 class="section-title">How Records Are Protected</h2>
                <div class="tribal-accent-bar"></div>
                <p class="section-subtitle">Every record follows the same protocol before anyone outside the archive can see it.</p>
            </div>

            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon">🌐</div>
                    <h3>Public</h3>
                    <p>Open to all visitors, published only after an administrator approves it and the community's consent is recorded.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon">🔒</div>
                    <h3>Restricted</h3>
                    <p>Visible only to registered community members whose accounts an administrator has approved.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon">🗝️</div>
                    <h3>Private</h3>
                    <p>Kept with the archive's custodians and never shown on the public website or in search results.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon">🙏</div>
                    <h3>Sacred &amp; Sensitive</h3>
                    <p>Sacred knowledge can never be made public, sensitive locations stay hidden, and confidential files are staff-only.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-title-wrap">
                <span class="section-badge">Our Guiding Ethos</span>
                <h2 class="section-title">Core Principles</h2>
                <div class="tribal-accent-bar"></div>
            </div>
            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon">🤝</div>
                    <h3>Communal Respect</h3>
                    <p>We honor Indigenous intellectual property, ritual sanctity, and community self-determination.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon">🏛️</div>
                    <h3>Careful Archiving</h3>
                    <p>Safeguarding cultural expressions against loss, misuse, and commercial appropriation.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon">🎓</div>
                    <h3>Respectful Education</h3>
                    <p>Non-commercial educational use is welcome with attribution to the originating communities.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon">✋</div>
                    <h3>Right to Withdraw</h3>
                    <p>Communities may withdraw consent at any time; withdrawn records are removed from public view immediately.</p>
                </div>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/includes/partials/public_footer.php'; ?>
