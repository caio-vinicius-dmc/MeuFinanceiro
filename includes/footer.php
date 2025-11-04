<?php
// includes/footer.php
?>

    <?php if (isLoggedIn()): ?>
        </main>

        <footer class="app-footer py-4">
            <div class="container-fluid">
                <div class="row align-items-center gy-3">
                    <div class="col-md-4 d-flex flex-column justify-content-center">
                        <div class="d-flex align-items-center">
                                <img src="<?php echo base_url('assets/img/logo-dmc.jpg'); ?>" alt="DMC" class="footer-logo me-2" />
                            <div>
                                <div class="fw-bold">DMC - Dynami Motion Century</div>
                                <small class="text-muted">&copy; 2025. Todos os direitos reservados.</small>
                            </div>
                        </div>
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
                            <div class="footer-version text-muted me-3">v1</div>
                            <a href="https://www.instagram.com/dynamic.motion.century" class="btn btn-outline-secondary btn-sm rounded-circle social-btn" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                            <a href="https://twitter.com/dynamicMcentury" class="btn btn-outline-secondary btn-sm rounded-circle social-btn" aria-label="twitter"><i class="bi bi-twitter"></i></a>
                            <!--<a href="#" class="btn btn-outline-secondary btn-sm rounded-circle social-btn" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>-->
                        </div>
                    </div>
                </div>
            </div>
        </footer>

    <?php endif; // Fim do if (isLoggedIn()) ?>

        <!-- Modal genérico para filtros mobile -->
        <div class="modal fade" id="mobileFiltersModal" tabindex="-1" aria-labelledby="mobileFiltersModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="mobileFiltersModalLabel">Filtros</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body" id="mobile-filters-modal-body">
                        <!-- Conteúdo do formulário de filtros será injetado via JS -->
                    </div>
                </div>
            </div>
        </div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<script src="<?php echo base_url('assets/js/scripts.js'); ?>"></script>

</body>
</html>