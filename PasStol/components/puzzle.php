<?php
// Компонент «капча-пазл» — переиспользуемая часть страниц входа и регистрации.
// Сборка: фрагменты (2×2) перетаскиваются/кликаются в ячейки поля; результат
// раскладки передаётся в скрытом поле cap_place и проверяется на сервере (captcha_lib.php).

// Стили компонента (одинаковые для index.php и register.php).
function component_puzzle_css(): string
{
    return '.puzzle-board{display:grid;grid-template-columns:repeat(2,160px);grid-auto-rows:90px;gap:4px;'
        . 'background:#EDF1F5;padding:4px;border-radius:10px;border:1px solid #BDC3C7;width:max-content;max-width:100%}'
        . '.puzzle-slot{border:2px dashed #BDC3C7;border-radius:8px;background:rgba(255,255,255,.65);'
        . 'display:flex;align-items:center;justify-content:center;overflow:hidden}'
        . '.puzzle-slot.filled{border-color:transparent;background:none}'
        . '.puzzle-frag{width:156px;height:86px;cursor:grab;border-radius:6px;'
        . 'box-shadow:0 3px 8px rgba(0,0,0,.25);display:block}'
        . '.puzzle-frag.selected{outline:3px solid #3498DB}'
        . '.puzzle-tray{display:grid;grid-template-columns:repeat(2,160px);gap:8px;margin-top:10px;padding:6px;'
        . 'background:#F7FAFC;border:1px dashed #BDC3C7;border-radius:10px;width:max-content;max-width:100%;min-height:0}'
        . '.puzzle-tray-hint{display:none}'
        . '.puzzle-tray.empty{display:flex;align-items:center;justify-content:center;width:100%;min-height:48px;padding:4px 10px}'
        . '.puzzle-tray.empty .puzzle-tray-hint{display:block;font-size:12px;color:#8A99A8;text-align:center}';
}

// Разметка компонента: поле из 4 ячеек + лента с фрагментами + скрытое поле раскладки.
function component_puzzle_html(): string
{
    $slots = '';
    for ($i = 0; $i < 4; $i++) $slots .= '<div class="puzzle-slot" data-slot="' . $i . '"></div>';

    $frags = '';
    for ($i = 0; $i < 4; $i++) $frags .= '<img class="puzzle-frag" draggable="true" data-frag="' . $i
        . '" src="captcha.php?d=' . $i . '" alt="">';

    $hint = '<span class="puzzle-tray-hint">Все фрагменты на поле. Перетащите фрагмент сюда '
        . '(или кликните по фрагменту, затем по этой области), чтобы вернуть его.</span>';

    return '<div class="puzzle-board" id="puzzleBoard">' . $slots . '</div>'
        . '<div class="puzzle-tray" id="puzzleTray">' . $frags . $hint . '</div>'
        . '<input type="hidden" name="cap_place" id="capPlace" value="[-1,-1,-1,-1]">';
}

// Подсказка под капчей (одинаковая на обеих страницах).
function component_puzzle_help(): string
{
    return '<p class="muted">Перетащите фрагмент в ячейку или кликните по фрагменту, затем по ячейке. '
        . 'Клик по ленте возвращает выбранный фрагмент назад.</p>';
}
