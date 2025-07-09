<div class="footer-selector-wrapper">

    <div class="container">
        <div class="footer-selector-content">
            <?php if($redirectBackMarkup): ?>
                <div class="footer-selector-info">
                    <?= wp_kses_post($redirectBackMarkup); ?>
                </div>
            <?php endif; ?>
            <div class="footer-redirect-list-container">
                <ul>
                    <?= $redirectListMarkup; ?>
                </ul>
            </div>
        </div>
    </div>

</div>
