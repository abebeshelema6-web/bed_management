
</div><!-- /.main-content -->
</div><!-- /.main-wrapper -->
<?php
// Flush output buffer if we started one
if (ob_get_level()) { ob_end_flush(); }
?>

<!-- Footer -->
<footer class="footer-bar text-center text-muted small py-2">
    <?= e(APP_NAME) ?> v<?= APP_VERSION ?> &copy; <?= date('Y') ?> &nbsp;|&nbsp; Kuyu General Hospital
</footer>

<div class="system-chat" id="systemChat">
    <section class="system-chat-panel" id="systemChatPanel" aria-label="System help assistant" hidden>
        <div class="system-chat-heading">
            <div>
                <strong data-chat-title>System Help</strong>
                <div class="small opacity-75" data-chat-subtitle>Offline help in your language</div>
            </div>
            <button type="button" class="system-chat-close" data-chat-close aria-label="Close chat">&times;</button>
        </div>
        <div class="system-chat-toolbar">
            <label class="visually-hidden" for="systemChatLanguage" data-chat-language-label>Chat language</label>
            <select id="systemChatLanguage" class="form-select form-select-sm" data-chat-language>
                <option value="en">English</option>
                <option value="am">አማርኛ</option>
                <option value="om">Afaan Oromoo</option>
            </select>
        </div>
        <div class="system-chat-messages" data-chat-messages aria-live="polite" aria-relevant="additions"></div>
        <div class="system-chat-suggestions" data-chat-suggestions></div>
        <form class="system-chat-form" data-chat-form>
            <label class="visually-hidden" for="systemChatInput" data-chat-input-label>Ask about the system</label>
            <input id="systemChatInput" type="text" class="form-control form-control-sm" maxlength="500" autocomplete="off" data-chat-input>
            <button type="submit" class="btn btn-primary btn-sm" data-chat-send aria-label="Send message"><i class="fas fa-paper-plane"></i></button>
        </form>
    </section>
    <button type="button" class="system-chat-toggle" data-chat-toggle aria-controls="systemChatPanel" aria-expanded="false">
        <i class="fas fa-comments me-1" aria-hidden="true"></i><span data-chat-toggle-label>Help</span>
    </button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="/kghbms/assets/js/app.js"></script>
<script src="/kghbms/assets/js/chatbot.js"></script>
<?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>
