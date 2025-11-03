<?php
// includes/footer.php
?>

    <?php if (isLoggedIn()): ?>
        </main>

        <footer class="app-footer py-4">
            <div class="container-fluid">
                <div class="row align-items-center gy-3">
                    <div class="col-md-4 d-flex flex-column justify-content-center">
                        <div class="fw-bold">DMC - Dynami Motion Century</div>
                        <small class="text-muted">&copy; 2025. Todos os direitos reservados.</small>
                    </div>

                    <div class="col-md-4 d-flex justify-content-center">
                        <nav class="footer-nav">
                            <a href="#" class="footer-link me-3">Suporte</a>
                            <a href="#" class="footer-link me-3">Política de Privacidade</a>
                            <a href="#" class="footer-link">Termos de Uso</a>
                        </nav>
                    </div>

                    <div class="col-md-4 d-flex justify-content-md-end justify-content-center">
                        <div class="d-flex align-items-center gap-2">
                            <div class="footer-version text-muted me-3">v1.9</div>
                            <a href="#" class="btn btn-outline-secondary btn-sm rounded-circle social-btn" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                            <a href="#" class="btn btn-outline-secondary btn-sm rounded-circle social-btn" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                            <a href="#" class="btn btn-outline-secondary btn-sm rounded-circle social-btn" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </footer>

    <?php endif; // Fim do if (isLoggedIn()) ?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<script src="<?php echo base_url('assets/js/scripts.js'); ?>"></script>

</body>
</html>