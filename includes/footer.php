<?php
// ============================================================
// SecondChance Mart - Shared HTML Footer
// ============================================================
?>
</main><!-- /main -->

<!-- ── Site Footer ──────────────────────────────────────── -->
<footer class="footer bg-dark text-white mt-5">
    <div class="container py-5">
        <div class="row g-4">
            <!-- Brand & About -->
            <div class="col-lg-3 col-md-6">
                <h5 class="text-success mb-3">🛒 SecondChance Mart</h5>
                <p class="text-muted small">Your trusted platform for clearance groceries. We help reduce food waste by connecting shoppers with near-expiry, overstock, and discounted supermarket products.</p>
                <div class="social-links mt-3">
                    <a href="#" class="text-muted me-2"><i class="fab fa-facebook fa-lg"></i></a>
                    <a href="#" class="text-muted me-2"><i class="fab fa-instagram fa-lg"></i></a>
                    <a href="#" class="text-muted me-2"><i class="fab fa-twitter fa-lg"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6">
                <h6 class="text-warning mb-3">Quick Links</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="<?= SITE_URL ?>/">Home</a></li>
                    <li><a href="<?= SITE_URL ?>/products.php">All Products</a></li>
                    <li><a href="<?= SITE_URL ?>/products.php?category=near-expiry">Near Expiry Deals</a></li>
                    <li><a href="<?= SITE_URL ?>/products.php?category=overstock">Overstock Clearance</a></li>
                    <?php if (!isLoggedIn()): ?>
                    <li><a href="<?= SITE_URL ?>/register.php">Create Account</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Categories -->
            <div class="col-lg-3 col-md-6">
                <h6 class="text-warning mb-3">Product Categories</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="<?= SITE_URL ?>/products.php?category=fruits-vegetables">🍎 Fruits & Vegetables</a></li>
                    <li><a href="<?= SITE_URL ?>/products.php?category=bakery">🍞 Bakery</a></li>
                    <li><a href="<?= SITE_URL ?>/products.php?category=dairy">🧀 Dairy Products</a></li>
                    <li><a href="<?= SITE_URL ?>/products.php?category=frozen-food">❄️ Frozen Food</a></li>
                    <li><a href="<?= SITE_URL ?>/products.php?category=drinks">🥤 Drinks</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div class="col-lg-4 col-md-6">
                <h6 class="text-warning mb-3">Contact & Support</h6>
                <ul class="list-unstyled text-muted small">
                    <li class="mb-2"><i class="fas fa-envelope text-success me-2"></i><?= EMAIL_ADMIN ?></li>
                    <li class="mb-2"><i class="fas fa-phone text-success me-2"></i>+65 6123 4567</li>
                    <li class="mb-2"><i class="fas fa-map-marker-alt text-success me-2"></i>123 Clearance Road, Singapore 123456</li>
                    <li class="mb-2"><i class="fas fa-clock text-success me-2"></i>Mon–Sat: 9am – 6pm</li>
                </ul>
                <!-- Newsletter -->
                <div class="mt-3">
                    <p class="small text-muted mb-2">Subscribe for deals:</p>
                    <div class="input-group input-group-sm">
                        <input type="email" class="form-control" placeholder="your@email.com">
                        <button class="btn btn-success" type="button">Subscribe</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Bar -->
    <div class="border-top border-secondary py-3">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center">
            <small class="text-muted">© <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</small>
            <small class="text-muted mt-2 mt-md-0">
                <i class="fas fa-leaf text-success me-1"></i>Helping reduce food waste, one purchase at a time.
            </small>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
