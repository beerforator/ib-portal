</div>
    </main>

    <footer class="glass-panel portal-footer">
        <div class="container portal-copy">
            <?= IbPortalSupport::richText(str_replace('[year]', date('Y'), $settings['footer'])) ?>
        </div>
    </footer>
</body>
</html>
