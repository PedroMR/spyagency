<?php
/**
 * Rules overlay widget — include this in any page.
 * Outputs the overlay div + inline toggle script.
 * Add a button with onclick="rulesOpen()" wherever you want the trigger.
 */
function rules_md_to_html(string $md): string {
    $lines = explode("\n", $md);
    $html  = '';
    $para  = [];

    $flush = function() use (&$para, &$html) {
        if (!$para) return;
        $text = implode(' ', $para);
        // Inline: **bold**
        $text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text);
        // Inline: *italic*
        $text = preg_replace('/\*(.+?)\*/u', '<em>$1</em>', $text);
        $html .= '<p>' . $text . '</p>';
        $para  = [];
    };

    foreach ($lines as $line) {
        if (preg_match('/^### (.+)/u', $line, $m)) {
            $flush();
            $html .= '<h4>' . htmlspecialchars($m[1], ENT_QUOTES) . '</h4>';
        } elseif (preg_match('/^## (.+)/u', $line, $m)) {
            $flush();
            $html .= '<h3>' . htmlspecialchars($m[1], ENT_QUOTES) . '</h3>';
        } elseif (preg_match('/^# (.+)/u', $line, $m)) {
            $flush();
            $html .= '<h2>' . htmlspecialchars($m[1], ENT_QUOTES) . '</h2>';
        } elseif (trim($line) === '') {
            $flush();
        } else {
            $para[] = htmlspecialchars(trim($line), ENT_QUOTES);
        }
    }
    $flush();
    return $html;
}

$_rules_path = __DIR__ . '/../../docs/rules.md';
$_rules_html = file_exists($_rules_path)
    ? rules_md_to_html(file_get_contents($_rules_path))
    : '<p>Rules not found.</p>';
?>
<div id="rules-overlay" class="rules-overlay" style="display:none" onclick="if(event.target===this)rulesClose()">
    <div class="rules-modal">
        <div class="rules-modal-header">
            <h2 style="margin:0">📖 Rules</h2>
            <button onclick="rulesClose()">✕</button>
        </div>
        <div class="rules-modal-body">
            <?= $_rules_html ?>
        </div>
    </div>
</div>
<script>
function rulesOpen()  { document.getElementById('rules-overlay').style.display = 'flex'; }
function rulesClose() { document.getElementById('rules-overlay').style.display = 'none'; }
</script>
