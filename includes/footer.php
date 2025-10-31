<?php
// includes/footer.php
?>

    <?php if (isLoggedIn()): ?>
        </main> 
        
        <footer class="app-footer"> 
            <div class="container-fluid">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                    
                    <div class="text-center text-md-start mb-3 mb-md-0 app-copyright">
                        &copy; 2025 DMC (Dynami Motion Century). Todos os direitos reservados.
                    </div>
                    
                    <div class="social-icons">
                        <a href="#" class="social-link" title="Visite-nos no Instagram">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="#" class="social-link" title="Siga-nos no LinkedIn">
                            <i class="bi bi-linkedin"></i>
                        </a>
                        <a href="#" class="social-link" title="Fale conosco no WhatsApp">
                            <i class="bi bi-whatsapp"></i>
                        </a>
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