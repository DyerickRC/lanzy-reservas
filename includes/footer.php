</main>
<?php if (empty($layoutAuth)): ?>
<footer class="lz-footer">
  <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="lz-brand lz-brand-sm"><span class="lz-brand-icon"><i class="bi bi-controller"></i></span>LANZY</span>
    <span>&copy; <?= date('Y') ?> Lanzy · Reserve. Jogue. Aproveite.</span>
  </div>
</footer>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= $basePath ?>assets/js/script.js?v=5"></script>
</body>
</html>
