    <?php if (!isset($hide_nav) || !$hide_nav): ?>
    </main>
    <footer class="bg-surface border-t border-outline-variant py-6 mt-auto pl-64 w-full">
        <div class="px-8 text-center text-muted text-sm font-medium">
            &copy; <?php echo date('Y'); ?> Tripistry. All rights reserved.
        </div>
    </footer>
    <?php endif; ?>
    <script src="<?php echo $base_url; ?>/js/api.js"></script>
    <?php if (isset($extra_js)): ?>
    <script src="<?php echo $base_url . '/' . $extra_js; ?>"></script>
    <?php endif; ?>
</body>
</html>