/**
 * ロゴ調整フォーム（FR-19 / Canvasリアルタイムプレビュー）。
 * 画像選択欄（data-logo-adjust-target 付き input[type=file]）で選ばれたファイルごとに、
 * 実際の画像を <canvas> に描画し、その上でロゴをドラッグして位置を調整できる。
 * 大きさ・濃さ（不透明度）はスライダーで調整し、いずれの操作もプレビューへ即時反映する。
 *
 * 送信される値は logo_pos_x[] / logo_pos_y[] / logo_scale[] / logo_opacity[] で、
 * 保存時の確定合成はサーバー側（PHP GD）が行う。プレビューはCanvas 2Dによる近似表示のため、
 * 最終結果とは完全には一致しない（投稿確認画面で実際の合成結果を確認できる）。
 * JSやCanvasが使えない場合はこの欄は表示されず、サーバー側の既定値が使われる。
 */
(function () {
    'use strict';

    var LOGO_SRC = '/assets/images/ksmc-emblem.png';
    var DEFAULTS = { posX: 90, posY: 90, scale: 15, opacity: 70 };
    var RANGES = {
        posX: { min: 0, max: 100 },
        posY: { min: 0, max: 100 },
        scale: { min: 10, max: 50 },
        opacity: { min: 0, max: 100 }
    };
    var PREVIEW_MAX_WIDTH = 360;

    var logoImage = new Image();
    var logoLoaded = false;
    logoImage.addEventListener('load', function () { logoLoaded = true; });
    logoImage.src = LOGO_SRC;

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    /**
     * スライダー1行を作る。$name が空なら送信対象にしない（位置はhidden inputで送るため）。
     * 返り値の sync() を呼ぶと、state の現在値でスライダーの表示を更新する。
     */
    function createRangeRow(labelText, name, key, state, stateKey, onChange, minOverride) {
        var row = document.createElement('div');
        row.className = 'logo-adjust-row';

        var label = document.createElement('label');
        label.textContent = labelText;

        var range = document.createElement('input');
        range.type = 'range';
        if (name) {
            range.name = name;
        }
        range.min = String(minOverride !== undefined ? minOverride : RANGES[key].min);
        range.max = String(RANGES[key].max);
        range.value = String(state[stateKey]);

        var valueOut = document.createElement('span');
        valueOut.className = 'logo-adjust-value';
        valueOut.textContent = String(state[stateKey]);

        range.addEventListener('input', function () {
            state[stateKey] = Number(range.value);
            valueOut.textContent = range.value;
            onChange();
        });

        label.appendChild(range);
        label.appendChild(valueOut);
        row.appendChild(label);

        return {
            row: row,
            range: range,
            sync: function () {
                var rounded = String(Math.round(state[stateKey]));
                range.value = rounded;
                valueOut.textContent = rounded;
            },
            /**
             * スライダーの下限を後から変更する（投稿区分の切り替え時に使用）。
             * 現在値が新しい下限を下回っている場合は下限に引き上げる。
             */
            applyMin: function (newMin) {
                range.min = String(newMin);
                if (state[stateKey] < newMin) {
                    state[stateKey] = newMin;
                }
                this.sync();
                onChange();
            }
        };
    }

    function buildBlock(file, number, opacityMin) {
        var state = {
            posX: DEFAULTS.posX,
            posY: DEFAULTS.posY,
            scale: DEFAULTS.scale,
            opacity: Math.max(DEFAULTS.opacity, opacityMin)
        };

        var block = document.createElement('div');
        block.className = 'logo-adjust-block';

        var title = document.createElement('p');
        title.className = 'logo-adjust-filename';
        title.textContent = '画像' + number + '（' + file.name + '）';
        block.appendChild(title);

        var numberHint = document.createElement('p');
        numberHint.className = 'hint';
        numberHint.textContent = '本文中でこの画像を挿入したい位置に [image:' + number + '] と入力してください。';
        block.appendChild(numberHint);

        var posXInput = document.createElement('input');
        posXInput.type = 'hidden';
        posXInput.name = 'logo_pos_x[]';
        posXInput.value = String(state.posX);

        var posYInput = document.createElement('input');
        posYInput.type = 'hidden';
        posYInput.name = 'logo_pos_y[]';
        posYInput.value = String(state.posY);

        block.appendChild(posXInput);
        block.appendChild(posYInput);

        var canvas = document.createElement('canvas');
        canvas.className = 'logo-preview-canvas';
        var ctx = canvas.getContext ? canvas.getContext('2d') : null;

        var hint = document.createElement('p');
        hint.className = 'hint';

        var syncFns = [];
        var photo = new Image();
        var photoLoaded = false;
        var objectUrl = URL.createObjectURL(file);

        function redraw() {
            posXInput.value = String(Math.round(state.posX));
            posYInput.value = String(Math.round(state.posY));
            for (var i = 0; i < syncFns.length; i++) {
                syncFns[i]();
            }

            if (!ctx || !photoLoaded) {
                return;
            }

            var cw = canvas.width;
            var ch = canvas.height;
            ctx.clearRect(0, 0, cw, ch);
            ctx.drawImage(photo, 0, 0, cw, ch);

            if (logoLoaded && logoImage.naturalWidth > 0) {
                var lw = cw * (state.scale / 100);
                var lh = lw * (logoImage.naturalHeight / logoImage.naturalWidth);
                var cx = cw * (state.posX / 100);
                var cy = ch * (state.posY / 100);
                ctx.save();
                ctx.globalAlpha = clamp(state.opacity / 100, 0, 1);
                ctx.drawImage(logoImage, cx - lw / 2, cy - lh / 2, lw, lh);
                ctx.restore();
            }
        }

        if (ctx) {
            block.appendChild(canvas);
        }
        block.appendChild(hint);

        photo.addEventListener('load', function () {
            photoLoaded = true;
            var ratio = photo.naturalHeight / photo.naturalWidth;
            var width = Math.min(PREVIEW_MAX_WIDTH, photo.naturalWidth || PREVIEW_MAX_WIDTH);
            canvas.width = Math.round(width);
            canvas.height = Math.round(width * (ratio || 0.75));
            hint.textContent = 'ロゴをドラッグして位置を調整できます。下のスライダーで大きさ・濃さを調整できます。';
            redraw();
            URL.revokeObjectURL(objectUrl);
        });
        photo.addEventListener('error', function () {
            if (canvas.parentNode) {
                canvas.parentNode.removeChild(canvas);
            }
            hint.textContent = 'この画像はプレビューを表示できません（保存時にロゴは合成されます）。';
            URL.revokeObjectURL(objectUrl);
        });
        photo.src = objectUrl;

        if (!logoLoaded) {
            logoImage.addEventListener('load', redraw, { once: true });
        }

        var posXRow = createRangeRow('ロゴの位置（左右）', null, 'posX', state, 'posX', redraw);
        var posYRow = createRangeRow('ロゴの位置（上下）', null, 'posY', state, 'posY', redraw);
        var scaleRow = createRangeRow('ロゴの大きさ', 'logo_scale[]', 'scale', state, 'scale', redraw);
        var opacityRow = createRangeRow('ロゴの濃さ（不透明度）', 'logo_opacity[]', 'opacity', state, 'opacity', redraw, opacityMin);
        syncFns.push(posXRow.sync, posYRow.sync, scaleRow.sync, opacityRow.sync);

        block.appendChild(posXRow.row);
        block.appendChild(posYRow.row);
        block.appendChild(scaleRow.row);
        block.appendChild(opacityRow.row);

        if (ctx) {
            var dragging = false;

            var applyPointer = function (ev) {
                var rect = canvas.getBoundingClientRect();
                if (rect.width === 0 || rect.height === 0) {
                    return;
                }
                state.posX = clamp((ev.clientX - rect.left) / rect.width * 100, 0, 100);
                state.posY = clamp((ev.clientY - rect.top) / rect.height * 100, 0, 100);
                redraw();
            };

            canvas.addEventListener('pointerdown', function (ev) {
                dragging = true;
                if (canvas.setPointerCapture) {
                    try { canvas.setPointerCapture(ev.pointerId); } catch (e) { /* noop */ }
                }
                applyPointer(ev);
                ev.preventDefault();
            });
            canvas.addEventListener('pointermove', function (ev) {
                if (!dragging) {
                    return;
                }
                applyPointer(ev);
                ev.preventDefault();
            });
            var endDrag = function (ev) {
                if (!dragging) {
                    return;
                }
                dragging = false;
                if (canvas.releasePointerCapture) {
                    try { canvas.releasePointerCapture(ev.pointerId); } catch (e) { /* noop */ }
                }
            };
            canvas.addEventListener('pointerup', endDrag);
            canvas.addEventListener('pointercancel', endDrag);
        }

        return { element: block, opacityRow: opacityRow };
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

        // 編集画面では既存画像の後ろに新規画像が並ぶため、本文プレースホルダー用の番号は
        // 既存画像の枚数分だけ繰り上げる（data-image-number-offset属性で指定）。
        var numberOffset = parseInt(input.getAttribute('data-image-number-offset') || '0', 10) || 0;

        // 個人の作品記事（post_type='individual'）における透過度の下限（%）。公式ブログは0%まで許容する。
        // 具体的な数値はサーバー側（App\Config\Uploads::LOGO_MIN_OPACITY_INDIVIDUAL）と揃える必要が
        // あるため、ハードコードせずdata属性経由でPHPから受け取る。
        var minOpacityIndividual = parseInt(input.getAttribute('data-opacity-min-individual') || '0', 10) || 0;

        // 投稿区分の切り替えラジオボタン（広報担当・管理者の新規投稿フォームのみ存在する）。
        // 存在しない画面（一般会員の新規投稿・編集画面）では、data-post-type属性の固定値を使う。
        var postTypeRadios = document.querySelectorAll('input[name="post_type"]');

        function currentPostType() {
            for (var i = 0; i < postTypeRadios.length; i++) {
                if (postTypeRadios[i].checked) {
                    return postTypeRadios[i].value;
                }
            }
            return input.getAttribute('data-post-type') || 'individual';
        }

        function currentOpacityMin() {
            return currentPostType() === 'official_blog' ? 0 : minOpacityIndividual;
        }

        var activeOpacityRows = [];

        input.addEventListener('change', function () {
            container.innerHTML = '';
            activeOpacityRows = [];

            if (!input.files || input.files.length === 0) {
                return;
            }

            var heading = document.createElement('p');
            heading.className = 'hint';
            heading.textContent = 'それぞれの画像について、実際の画像上でロゴの位置・大きさ・濃さを調整できます（未調整の場合は既定値を使用します）。';
            container.appendChild(heading);

            var opacityMin = currentOpacityMin();
            for (var i = 0; i < input.files.length; i++) {
                var built = buildBlock(input.files[i], numberOffset + i + 1, opacityMin);
                container.appendChild(built.element);
                activeOpacityRows.push(built.opacityRow);
            }
        });

        for (var r = 0; r < postTypeRadios.length; r++) {
            postTypeRadios[r].addEventListener('change', function () {
                var newMin = currentOpacityMin();
                for (var j = 0; j < activeOpacityRows.length; j++) {
                    activeOpacityRows[j].applyMin(newMin);
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var inputs = document.querySelectorAll('input[type="file"][data-logo-adjust-target]');
        for (var i = 0; i < inputs.length; i++) {
            init(inputs[i]);
        }
    });
})();
