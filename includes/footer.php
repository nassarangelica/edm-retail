</main>
<footer class="app-footer mt-5 py-4 text-center small">
  <div class="container">
    <?= e(APP_NAME) ?> &middot; Enterprise Data Management (EDM) project.
    <div class="text-muted">We never store card numbers (PCI DSS) and you can export or delete your data anytime (GDPR).</div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<?= $pageScripts ?? '' ?>
</body>
</html>
