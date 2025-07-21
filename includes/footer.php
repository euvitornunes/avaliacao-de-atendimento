        </main>

        <?php if(!in_array(basename($_SERVER['PHP_SELF']), ['screen1.php', 'screen4.php', 'login.php'])): ?>
        <footer class="bg-dark text-white py-4 mt-auto">
            <div class="container">
                <div class="row gy-3">
                    <div class="col-lg-4">
                        <h5 class="text-primary mb-3" style="color: #4cc9f0 !important;">Sistema de Avaliação</h5>
                        <p class="mb-0">Solução profissional para feedback de atendimento ao cliente.</p>
                    </div>
                    <div class="col-lg-4">
                        <h5 class="text-primary mb-3" style="color: #4cc9f0 !important;">Links Úteis</h5>
                        <ul class="list-unstyled">
                            <li><a href="<?= BASE_URL ?>/admin/dashboard.php" style="color: white; text-decoration: none;">Dashboard</a></li>
                            <li><a href="<?= BASE_URL ?>/admin/funcionarios.php" style="color: white; text-decoration: none;">Funcionários</a></li>
                            <li><a href="<?= BASE_URL ?>/admin/relatorios.php" style="color: white; text-decoration: none;">Relatórios</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <h5 class="text-primary mb-3" style="color: #4cc9f0 !important;">Contato</h5>
                        <p class="mb-1"><i class="fas fa-envelope me-2"></i> email@vitor.com</p>
                        <p class="mb-0"><i class="fas fa-phone me-2"></i> (74) 99999-9999</p>
                    </div>
                </div>
                <hr style="background-color: #6c757d; margin: 1.5rem 0;">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-0">Versão 1.0.0</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-0">© <?= date('Y') ?> Todos os direitos reservados - VitorNunes</p>
                    </div>
                </div>
            </div>
        </footer>
        <?php endif; ?>

        <!-- Bootstrap JS Bundle with Popper -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        
        <!-- Scripts Customizados -->
        <script src="<?= BASE_URL ?>/assets/js/scripts.js"></script>
        
        <style>
            /* Estilos incorporados para o footer */
            footer {
                background-color: #212529;
                padding: 2rem 0;
            }
            
            footer a:hover {
                color: #4cc9f0 !important;
                text-decoration: underline !important;
            }
            
            footer h5 {
                font-size: 1.1rem;
                font-weight: 600;
                margin-bottom: 1rem;
            }
            
            footer ul {
                padding-left: 0;
            }
            
            footer ul li {
                margin-bottom: 0.5rem;
            }
            
            @media (max-width: 768px) {
                footer .text-lg-end {
                    text-align: left !important;
                    margin-top: 1.5rem;
                }
            }
        </style>
    </body>
</html>