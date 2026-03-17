<?php
declare(strict_types=1);

$jsPath = ROOT_PATH . '/assets/js/app.js';
$jsVersion = is_file($jsPath) ? (string)filemtime($jsPath) : '1';
?>
</main>
<footer class="footer">
    <div class="container">
        <p>&copy; <?php echo date('Y'); ?> <?php echo esc(APP_NAME); ?> All rights reserved.</p>
    </div>
</footer>
<script src="<?php echo BASE_URL; ?>/assets/js/app.js?v=<?php echo esc($jsVersion); ?>"></script>
</body>
</html>
