<?php
/**
 * footer_customer.php — penutup halaman pelanggan.
 * Variabel opsional: $skrip (HTML <script> tambahan)
 */
?>
</main>

<footer class="sitefoot">
    <div class="sitefoot-inner">
        <span>Cuci Sepatu &mdash; booking online &amp; riwayat cucian</span>
        <a href="<?php echo e(url('tracking.php')); ?>">Lacak pesanan tanpa akun</a>
    </div>
</footer>

<?php if (!empty($skrip)) { echo $skrip; } ?>
</body>
</html>