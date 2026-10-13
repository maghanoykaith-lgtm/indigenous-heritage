<?php
?>
    </main>
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3><span>🌿</span> Indigenous Heritage Portal</h3>
                    <p>Dedicated to respectful digital documentation, preservation, and celebration of indigenous cultures, sustainable arts, and ancestral memories.</p>
                    <p class="footer-note">Developed in collaboration with cultural researchers and indigenous knowledge custodians.</p>
                </div>

                <div class="footer-col">
                    <h4>Explore Archives</h4>
                    <ul class="footer-links">
                        <li><a href="<?php echo e(url('crafts.php')); ?>">Master Crafts</a></li>
                        <li><a href="<?php echo e(url('traditions.php')); ?>">Rituals &amp; Ceremonies</a></li>
                        <li><a href="<?php echo e(url('materials.php')); ?>">Native Materials</a></li>
                        <li><a href="<?php echo e(url('stories.php')); ?>">Oral Stories &amp; Folklore</a></li>
                        <li><a href="<?php echo e(url('gallery.php')); ?>">Visual Archives</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>About &amp; Portal</h4>
                    <ul class="footer-links">
                        <li><a href="<?php echo e(url('about.php')); ?>">Our Preservation Mission</a></li>
                        <li><a href="<?php echo e(url('contact.php')); ?>">Contact &amp; Inquiries</a></li>
                        <li><a href="<?php echo e(url('register.php')); ?>">Member Registration</a></li>
                        <li><a href="<?php echo e(url('user_dashboard.php')); ?>">User Dashboard</a></li>
                        <li><a href="<?php echo e(url('admin/login.php')); ?>">Admin Portal Login</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Cultural Sanctuary</h4>
                    <p class="footer-text">
                        <strong>Location:</strong> Cordillera &amp; Southern Indigenous Regions, Philippines<br>
                        <strong>Email:</strong> <?php echo e(app_config('contact_email')); ?><br>
                        <strong>Status:</strong> Active Cultural Archive
                    </p>
                    <a href="<?php echo e(url('admin/login.php')); ?>" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">Administrative Console 🔐</a>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Indigenous Cultural Heritage Portal. All cultural knowledge belongs to the respective indigenous communities.</p>
            </div>
        </div>
    </footer>

    <script src="<?php echo e(asset('js/script.js')); ?>"></script>
</body>
</html>
