<?php
// includes/footer_public.php
// Footer otimizado para páginas públicas (ex: login) — sem scripts nem fechamento de body/html
?>

<footer class="public-footer py-4 mt-4">
    <div class="container-fluid">
        <div class="row align-items-center gy-3">
            <div class="col-12 col-md-12 d-flex align-items-center gap-3">
                <img src="<?php echo base_url('assets/img/logo-dmc.jpg'); ?>" alt="DMC" class="footer-logo">
                <div class="fw-bold">Dynamic Motion Century</div>
            </div>
        </div>
        <div class="row gy-3">
            <div class="col-12 d-flex justify-content-center align-items-center gap-2">
                <div class="footer-version text-muted">v1.4</div>
                <a href="https://www.instagram.com/dynamic.motion.century" class="btn btn-outline-secondary btn-sm rounded-circle social-btn" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                <a href="https://twitter.com/dynamicMcentury" class="btn btn-outline-secondary btn-sm rounded-circle social-btn" aria-label="twitter"><i class="bi bi-twitter"></i></a>
            </div>
        </div>
    </div>
</footer>
