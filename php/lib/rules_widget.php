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
    $table = [];
    $list  = [];
    $quote = [];

    $inline = function(string $text): string {
        $text = preg_replace('/\*\*(.+?)\*\*|__(.+?)__/u', '<strong>$1$2</strong>', $text);
        $text = preg_replace('/\*(.+?)\*|_(.+?)_/u', '<em>$1$2</em>', $text);
        return $text;
    };

    $flush = function() use (&$para, &$html, $inline) {
        if (!$para) return;
        $html .= '<p>' . $inline(implode(' ', $para)) . '</p>';
        $para  = [];
    };

    $flushList = function() use (&$list, &$html, $inline) {
        if (!$list) return;
        $html .= '<ul>';
        foreach ($list as $item)
            $html .= '<li>' . $inline(htmlspecialchars($item, ENT_QUOTES)) . '</li>';
        $html .= '</ul>';
        $list = [];
    };

    $flushQuote = function() use (&$quote, &$html, $inline) {
        if (!$quote) return;
        $html .= '<blockquote>' . $inline(implode(' ', $quote)) . '</blockquote>';
        $quote = [];
    };

    $flushTable = function() use (&$table, &$html) {
        if (!$table) return;
        $parseRow = fn($line) => array_map('trim', explode('|', trim($line, "| \t")));
        $rows = array_values(array_filter($table, fn($r) => !preg_match('/^\|[\s|:-]+\|/', trim($r))));
        if (!$rows) { $table = []; return; }
        $html .= '<table class="rules-table">';
        $html .= '<thead><tr>';
        foreach ($parseRow($rows[0]) as $cell)
            $html .= '<th>' . htmlspecialchars($cell, ENT_QUOTES) . '</th>';
        $html .= '</tr></thead><tbody>';
        for ($i = 1; $i < count($rows); $i++) {
            $html .= '<tr>';
            foreach ($parseRow($rows[$i]) as $cell)
                $html .= '<td>' . htmlspecialchars($cell, ENT_QUOTES) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $table = [];
    };

    foreach ($lines as $line) {
        if (preg_match('/^\|/', trim($line))) {
            $flush(); $flushList(); $flushQuote();
            $table[] = $line;
        } elseif (preg_match('/^[*\-] (.+)/u', $line, $m)) {
            $flush(); $flushTable(); $flushQuote();
            $list[] = $m[1];
        } elseif (preg_match('/^> (.+)/u', $line, $m)) {
            $flush(); $flushTable(); $flushList();
            $quote[] = htmlspecialchars($m[1], ENT_QUOTES);
        } elseif (preg_match('/^### (.+)/u', $line, $m)) {
            $flush(); $flushTable(); $flushList(); $flushQuote();
            $html .= '<h4>' . htmlspecialchars($m[1], ENT_QUOTES) . '</h4>';
        } elseif (preg_match('/^## (.+)/u', $line, $m)) {
            $flush(); $flushTable(); $flushList(); $flushQuote();
            $html .= '<h3>' . htmlspecialchars($m[1], ENT_QUOTES) . '</h3>';
        } elseif (preg_match('/^# (.+)/u', $line, $m)) {
            $flush(); $flushTable(); $flushList(); $flushQuote();
            $html .= '<h2>' . htmlspecialchars($m[1], ENT_QUOTES) . '</h2>';
        } elseif (trim($line) === '') {
            $flush(); $flushTable(); $flushList(); $flushQuote();
        } else {
            $flushTable(); $flushList(); $flushQuote();
            $para[] = htmlspecialchars(trim($line), ENT_QUOTES);
        }
    }
    $flush();
    $flushTable();
    $flushList();
    $flushQuote();
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
