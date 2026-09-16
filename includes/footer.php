<footer class="site-footer">
    <?php $business = require BASE_PATH . '/config/business.php'; ?>
    <div class="container">
        <div>
            <h4>YVOLUTION CUSTOM APPAREL</h4>
            <p style="max-width:320px;font-size:14px;">Custom jerseys, uniforms, and team merch — designed, printed, and delivered by a shop that plays for keeps.</p>
            <p style="font-size:13px;color:var(--c-muted);margin-top:12px;">📍 <?= e($business['location']['address']) ?></p>
        </div>
        <div>
            <h4>Explore</h4>
            <a href="<?= BASE_URL ?>/public/index.php#products">Products</a>
            <a href="<?= BASE_URL ?>/public/index.php#services">Services</a>
            <a href="<?= BASE_URL ?>/public/index.php#packages">Packages</a>
            <a href="<?= BASE_URL ?>/public/index.php#promotions">Promotions</a>
            <a href="<?= BASE_URL ?>/public/size_guide.php">Size Guide</a>
        </div>
        <div>
            <h4>Get in Touch</h4>
            <a href="<?= BASE_URL ?>/public/index.php#contact">Contact Us</a>
            <a href="<?= BASE_URL ?>/auth/login.php">Track My Order</a>
        </div>
    </div>
    <div class="footer-bottom">&copy; <?= date('Y') ?> Yvolution Custom Apparel. All rights reserved.</div>
</footer>
<script>
(function () {
    const reveals = document.querySelectorAll('.reveal');
    if (!reveals.length) return;

    if (!('IntersectionObserver' in window)) {
        reveals.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.12,
        rootMargin: '0px 0px -40px 0px'
    });

    reveals.forEach((el) => observer.observe(el));
})();
</script>
<script
  src="https://www.tuqlas.com/chatbot.js"
  data-key="tq_live_0383051e5bca626621f82e145699b061eee7f2f9"
  data-api="https://www.tuqlas.com"
  defer
></script>
</body>
</html>
