    <?php
    // ============================================
    // FOOTER DO E-COMMERCE GAMER
    // ============================================
    // As variáveis $site_name_from_db, $site_slogan_from_db, etc.
    // já estão disponíveis aqui porque o header.php as carregou.
    ?>
    <footer class="footer">
        <div class="container">
            <div class="footer-columns">
                <div class="footer-col col-1">
                    <div class="logo-slogan">
                        <img src="<?= getSiteUrl($site_logo_path_from_db); ?>" alt="<?= $site_name_from_db ?> Logo">
                        <p class="slogan"><?= $site_slogan_from_db ?></p>
                    </div>
                </div>
                <div class="footer-col col-2">
                    <h3>Links Úteis</h3>
                    <ul>
                        <li><a href="<?= getSiteUrl('pages/sobre.php'); ?>">Sobre Nós</a></li>
                        <li><a href="<?= getSiteUrl('pages/contato.php'); ?>">Contato</a></li>
                        <li><a href="<?= getSiteUrl('pages/politica-privacidade.php'); ?>">Política de Privacidade</a></li>
                        <li><a href="<?= getSiteUrl('pages/termos-uso.php'); ?>">Termos de Uso</a></li>
                        <li><a href="<?= getSiteUrl('pages/trocas-devolucoes.php'); ?>">Trocas e Devoluções</a></li>
                    </ul>
                </div>
                <div class="footer-col col-3">
                    <h3>Contatos</h3>
                    <p><i class="fas fa-map-marker-alt"></i> <?= $site_address_from_db ?></p>
                    <p><i class="fas fa-phone"></i> <?= $contact_phone_from_db ?></p>
                    <p><i class="fas fa-envelope"></i> <a href="mailto:<?= $contact_email_from_db ?>"><?= $contact_email_from_db ?></a></p>
                </div>
                <div class="footer-col col-4">
                    <h3>Redes Sociais</h3>
                    <div class="social-icons">
                        <a href="<?= $social_facebook_from_db ?>" target="_blank" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="<?= $social_instagram_from_db ?>" target="_blank" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <!-- Se você tiver Twitter e YouTube, pode adicionar campos para eles nas configurações -->
                        <a href="#" target="_blank" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" target="_blank" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; <?= date('Y'); ?> <?= $site_name_from_db ?>. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>
</body>
</html>