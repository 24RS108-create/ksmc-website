/**
 * ロゴ調整フォーム（FR-19）。画像選択欄（#images）で選ばれたファイルの数だけ、
 * ロゴの位置・サイズ・透過度を調整するスライダーを動的に生成する。
 * JSが無効な場合はこの欄は表示されず、サーバー側で既定値（Uploads::LOGO_DEFAULT_*）を使用する。
 */
(function () {
    'use strict';

    var DEFAULTS = { posX: 90, posY: 90, scale: 15, opacity: 70 };
    var RANGES = {
        posX: { min: 0, max: 100 },
        posY: { min: 0, max: 100 },
        scale: { min: 10, max: 50 },
        opacity: { min: 0, max: 100 }
    };

    function buildField(labelText, name, key, defaultValue) {
        var row = document.createElement('div');
        row.className = 'logo-adjust-row';

        var label = document.createElement('label');
        label.textContent = labelText;

        var range = document.createElement('input');
        range.type = 'range';
        range.name = name;
        range.min = String(RANGES[key].min);
        range.max = String(RANGES[key].max);
        range.value = String(defaultValue);

        var value = document.createElement('span');
        value.className = 'logo-adjust-value';
        value.textContent = String(defaultValue);

        range.addEventListener('input', function () {
            value.textContent = range.value;
        });

        label.appendChild(range);
        label.appendChild(value);
        row.appendChild(label);

        return row;
    }

    function buildBlock(file) {
        var block = document.createElement('div');
        block.className = 'logo-adjust-block';

        var title = document.createElement('p');
        title.className = 'logo-adjust-filename';
        title.textContent = file.name;
        block.appendChild(title);

        block.appendChild(buildField('ロゴの位置（左右）', 'logo_pos_x[]', 'posX', DEFAULTS.posX));
        block.appendChild(buildField('ロゴの位置（上下）', 'logo_pos_y[]', 'posY', DEFAULTS.posY));
        block.appendChild(buildField('ロゴのサイズ', 'logo_scale[]', 'scale', DEFAULTS.scale));
        block.appendChild(buildField('ロゴの透過度', 'logo_opacity[]', 'opacity', DEFAULTS.opacity));

        return block;
    }

    function init(input) {
        var containerId = input.getAttribute('data-logo-adjust-target');
        if (!containerId) {
            return;
        }
        var container = document.getElementById(containerId);
        if (!container) {
            return;
        }

        input.addEventListener('change', function () {
            container.innerHTML = '';

            if (!input.files || input.files.length === 0) {
                return;
            }

            var heading = document.createElement('p');
            heading.className = 'hint';
            heading.textContent = 'それぞれの画像に合成するロゴの位置・サイズ・透過度を調整できます（未調整の場合は既定値を使用します）。';
            container.appendChild(heading);

            for (var i = 0; i < input.files.length; i++) {
                container.appendChild(buildBlock(input.files[i]));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var inputs = document.querySelectorAll('input[type="file"][data-logo-adjust-target]');
        for (var i = 0; i < inputs.length; i++) {
            init(inputs[i]);
        }
    });
})();
