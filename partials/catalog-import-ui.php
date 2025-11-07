<?php
if (!defined('QUOTABASE_SYSTEM')) {
    require_once __DIR__ . '/../config.php';
}

function render_catalog_import_ui(string $type = 'product') {
    $type = $type === 'service' ? 'service' : 'product';
    $type_label = $type === 'service' ? '服務' : '產品';
    $panel_id = 'catalog-import-panel-' . $type;
    $result_id = 'catalog-import-result-' . $type;
    $form_id = 'catalog-import-form-' . $type;
    $sample_lines = [
        "type\tsku\tname\tunit\tcurrency\tunit_price\ttax_rate\tcategory_path\tactive",
        "product\tPRO-001\t桌上型切割機\tpcs\tTWD\t1200\t5\t設備 > 切割\t1",
        "service\tSRV-010\t現場維護\ttime\tTWD\t3500\t0\t維保服務\t1"
    ];
    ?>
    <button type="button" class="btn btn-outline btn-compact" data-catalog-import-toggle="<?php echo h($panel_id); ?>">
        TXT 批次匯入
    </button>

    <section class="catalog-import-panel" id="<?php echo h($panel_id); ?>" hidden>
        <div class="catalog-import-panel__header">
            <h3>匯入 <?php echo h($type_label); ?></h3>
            <button type="button" class="catalog-import-close" data-catalog-import-toggle="<?php echo h($panel_id); ?>">
                &times;
            </button>
        </div>
        <div class="catalog-import-panel__body">
            <ol class="catalog-import-steps">
                <li>下載模板或複製以下範例，建議使用 <strong>Tab</strong> 或 <code>|</code> 做為分隔。</li>
                <li>欄位順序：<code>type, sku, name, unit, currency, unit_price, tax_rate, category_path, active</code>；type 可留空由面板預設值套用。</li>
                <li>檔案須為 UTF-8 編碼之 .txt（≤ 1MB），單位請僅使用系統支援代碼，如 <code>pcs</code>/<code>set</code>/<code>time</code>/<code>hour</code> 等。</li>
            </ol>
            <div class="catalog-import-template">
                <div class="template-label">範例</div>
                <pre><?php echo h(implode("\n", $sample_lines)); ?></pre>
                <div class="template-actions">
                    <button type="button" class="btn btn-secondary btn-compact" data-template-copy="<?php echo h(implode("\n", $sample_lines)); ?>">
                        複製範例
                    </button>
                    <button type="button" class="btn btn-outline btn-compact" data-template-download="<?php echo h(implode("\n", $sample_lines)); ?>" data-template-type="<?php echo h($type); ?>">
                        下載範例 TXT
                    </button>
                </div>
            </div>
            <form id="<?php echo h($form_id); ?>" class="catalog-import-form" data-catalog-import-form>
                <?php echo csrf_input(); ?>
                <input type="hidden" name="type" value="<?php echo h($type); ?>">
                <div class="form-field-group">
                    <label>策略</label>
                    <label class="inline-radio">
                        <input type="radio" name="strategy" value="skip" checked>
                        跳過重複 SKU
                    </label>
                    <label class="inline-radio">
                        <input type="radio" name="strategy" value="overwrite">
                        覆蓋重複 SKU
                    </label>
                </div>
                <div class="form-field-group">
                    <label for="<?php echo h($form_id); ?>-file">選擇 TXT 檔案</label>
                    <input type="file" id="<?php echo h($form_id); ?>-file" name="file" accept=".txt" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">開始匯入</button>
                    <button type="button" class="btn btn-secondary" data-catalog-import-toggle="<?php echo h($panel_id); ?>">取消</button>
                </div>
            </form>
            <div class="catalog-import-result" id="<?php echo h($result_id); ?>"></div>
        </div>
    </section>

    <script>
    (function() {
        if (window.__catalogImportInitialized__) {
            return;
        }
        window.__catalogImportInitialized__ = true;

        const typeLabels = { product: '產品', service: '服務' };
        const strategyLabels = { skip: '跳過重複', overwrite: '覆蓋重複' };
        const actionLabels = { created: '新增', updated: '覆蓋', skipped: '跳過', error: '錯誤' };
        const errorMessages = {
            METHOD_NOT_ALLOWED: '請以 POST 方式上傳資料。',
            UNAUTHORIZED: '尚未登入，請重新登入後再試。',
            INVALID_CSRF_TOKEN: 'CSRF 驗證失敗，請重新整理頁面後再試。',
            INVALID_TYPE: '匯入類型不正確。',
            INVALID_STRATEGY: '匯入策略不正確。',
            UPLOAD_FAILED: '檔案上傳失敗，請重新選擇檔案。',
            FILE_TOO_LARGE: '檔案不可超過 1MB。',
            INVALID_FILE_TYPE: '僅支援 .txt 純文字檔。',
            FILE_OPEN_FAILED: '伺服器無法讀取上傳檔案。',
            INTERNAL_ERROR: '伺服器錯誤，請稍後再試。'
        };

        const escapeHtml = (value) => {
            if (value === undefined || value === null) {
                return '';
            }
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        const truncate = (value, max = 80) => {
            const text = String(value ?? '');
            return text.length > max ? `${text.slice(0, max)}…` : text;
        };

        const formatNumber = (value) => {
            const num = Number(value);
            if (!Number.isFinite(num)) {
                return '0';
            }
            return new Intl.NumberFormat('zh-TW').format(num);
        };

        const resolveErrorMessage = (code) => errorMessages[code] || '匯入失敗，請稍後再試。';

        const copyText = (text) => {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(text);
            }
            return new Promise((resolve, reject) => {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                try {
                    document.execCommand('copy');
                    resolve();
                } catch (error) {
                    reject(error);
                } finally {
                    document.body.removeChild(textarea);
                }
            });
        };

        const buildSummaryText = (summary) => {
            const type = typeLabels[summary.type] || summary.type || '—';
            const strategy = strategyLabels[summary.strategy] || summary.strategy || '—';
            return [
                `匯入類型: ${type}`,
                `策略: ${strategy}`,
                `處理總筆數: ${summary.total ?? 0}`,
                `新增: ${summary.created ?? 0}`,
                `覆蓋: ${summary.updated ?? 0}`,
                `跳過: ${summary.skipped ?? 0}`
            ].join('\n');
        };

        const buildErrorsText = (errors = []) => {
            if (!errors.length) {
                return '沒有錯誤資料。';
            }
            const lines = ['line\tmessage\traw'];
            errors.forEach((err) => {
                const line = err.line ?? '-';
                const message = (err.message || '').replace(/\s+/g, ' ').trim();
                const raw = err.raw ?? '';
                lines.push(`${line}\t${message}\t${raw}`);
            });
            return lines.join('\n');
        };

        const buildFullReport = (summary) => {
            const sections = ['=== 匯入摘要 ===', buildSummaryText(summary)];

            if ((summary.events || []).length) {
                sections.push('=== 處理紀錄 ===');
                summary.events.forEach((evt) => {
                    sections.push([
                        evt.action,
                        evt.line ?? '-',
                        evt.sku ?? '',
                        evt.name ?? '',
                        evt.message ?? ''
                    ].join('\t'));
                });
            }

            if ((summary.skipped_details || []).length) {
                sections.push('=== 跳過原因 ===');
                summary.skipped_details.forEach((item) => {
                    sections.push([
                        item.line ?? '-',
                        item.sku ?? '',
                        item.reason ?? ''
                    ].join('\t'));
                });
            }

            sections.push('=== 錯誤明細 ===');
            if ((summary.errors || []).length) {
                sections.push(buildErrorsText(summary.errors));
            } else {
                sections.push('無錯誤');
            }

            return sections.join('\n\n');
        };

        const downloadText = (filename, content) => {
            const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        };

        const getResultBox = (target) => {
            const panel = target.closest('.catalog-import-panel');
            if (!panel) {
                return null;
            }
            return panel.querySelector('.catalog-import-result');
        };

        const getBadgeClass = (action) => {
            switch (action) {
                case 'created':
                    return 'badge badge-success';
                case 'updated':
                    return 'badge badge-info';
                case 'skipped':
                    return 'badge badge-warning';
                default:
                    return 'badge badge-danger';
            }
        };

        const renderResult = (resultBox, summary) => {
            if (!resultBox) {
                return;
            }
            resultBox.__importResult = summary;
            const type = escapeHtml(typeLabels[summary.type] || summary.type || '—');
            const strategy = escapeHtml(strategyLabels[summary.strategy] || summary.strategy || '—');

            const statsHtml = `
                <div class="catalog-import-result__summary">
                    <div>
                        <div class="label">匯入類型</div>
                        <div class="value">${type}</div>
                    </div>
                    <div>
                        <div class="label">策略</div>
                        <div class="value">${strategy}</div>
                    </div>
                    <div>
                        <div class="label">處理筆數</div>
                        <div class="value">${formatNumber(summary.total || 0)}</div>
                    </div>
                    <div>
                        <div class="label text-success">新增</div>
                        <div class="value">${formatNumber(summary.created || 0)}</div>
                    </div>
                    <div>
                        <div class="label">覆蓋</div>
                        <div class="value">${formatNumber(summary.updated || 0)}</div>
                    </div>
                    <div>
                        <div class="label text-warning">跳過</div>
                        <div class="value">${formatNumber(summary.skipped || 0)}</div>
                    </div>
                </div>
            `;

            const events = summary.events || [];
            let eventsHtml = '';
            if (events.length) {
                const visibleEvents = events.slice(0, 6);
                const extraCount = events.length - visibleEvents.length;
                const itemsHtml = visibleEvents.map((evt) => `
                    <li>
                        <span class="${getBadgeClass(evt.action)}">${actionLabels[evt.action] || evt.action}</span>
                        <span class="event-label">${escapeHtml(evt.sku || '（無 SKU）')}</span>
                        <span class="event-name">${escapeHtml(evt.name || '')}</span>
                        <span class="event-meta">行 ${evt.line ?? '—'}</span>
                        ${evt.message ? `<span class="event-meta">${escapeHtml(evt.message)}</span>` : ''}
                    </li>
                `).join('');
                const extraHtml = extraCount > 0
                    ? `<li class="event-meta">... 其餘 ${extraCount} 筆請下載報表查看</li>`
                    : '';
                eventsHtml = `
                    <div class="catalog-import-result__list">
                        <div class="section-title">最近處理</div>
                        <ul>${itemsHtml}${extraHtml}</ul>
                    </div>
                `;
            }

            const skipped = summary.skipped_details || [];
            let skippedHtml = '';
            if (skipped.length) {
                const limited = skipped.slice(0, 6).map((item) => `
                    <li>
                        <strong>${escapeHtml(item.sku || '—')}</strong>
                        <span class="event-meta">行 ${item.line ?? '—'}</span>
                        <span class="event-meta">${escapeHtml(item.reason || '')}</span>
                    </li>
                `).join('');
                const extra = skipped.length > 6 ? `<li class="event-meta">... 其餘 ${skipped.length - 6} 筆請下載報表查看</li>` : '';
                skippedHtml = `
                    <div class="catalog-import-result__list">
                        <div class="section-title">跳過記錄</div>
                        <ul>${limited}${extra}</ul>
                    </div>
                `;
            }

            const errors = summary.errors || [];
            let errorsHtml = '';
            if (errors.length) {
                const limited = errors.slice(0, 8).map((err) => `
                    <li>
                        <span class="event-meta">行 ${err.line ?? '—'}</span>
                        <span>${escapeHtml(err.message || '')}</span>
                        ${err.raw ? `<span class="event-meta">${escapeHtml(truncate(err.raw, 60))}</span>` : ''}
                    </li>
                `).join('');
                const extra = errors.length > 8 ? `<li class="event-meta">... 其餘 ${errors.length - 8} 筆請下載錯誤清單查看</li>` : '';
                errorsHtml = `
                    <div class="catalog-import-result__list catalog-import-result__errors">
                        <div class="section-title">錯誤明細</div>
                        <ul>${limited}${extra}</ul>
                    </div>
                `;
            }

            const actionButtons = [];
            actionButtons.push('<button type="button" class="btn btn-secondary btn-compact" data-import-copy="summary">複製摘要</button>');
            if (errors.length) {
                actionButtons.push('<button type="button" class="btn btn-outline btn-compact" data-import-download="errors">下載錯誤清單</button>');
            }
            if (events.length || skipped.length || errors.length) {
                actionButtons.push('<button type="button" class="btn btn-outline btn-compact" data-import-download="full">下載完整報表</button>');
            }

            const actionsHtml = actionButtons.length
                ? `<div class="catalog-import-result__actions">${actionButtons.join('')}</div>`
                : '';

            resultBox.innerHTML = [statsHtml, eventsHtml, skippedHtml, errorsHtml, actionsHtml].filter(Boolean).join('');
            resultBox.classList.remove('error');
            resultBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        };

        const togglePanel = (id) => {
            const panel = document.getElementById(id);
            if (!panel) {
                return;
            }
            panel.hidden = !panel.hidden;
        };

        document.addEventListener('click', (event) => {
            const toggleTarget = event.target.closest('[data-catalog-import-toggle]');
            if (toggleTarget) {
                event.preventDefault();
                togglePanel(toggleTarget.getAttribute('data-catalog-import-toggle'));
                return;
            }

            const templateBtn = event.target.closest('[data-template-copy]');
            if (templateBtn) {
                event.preventDefault();
                const content = templateBtn.getAttribute('data-template-copy');
                copyText(content).then(() => {
                    templateBtn.textContent = '已複製';
                    templateBtn.disabled = true;
                    setTimeout(() => {
                        templateBtn.textContent = '複製範例';
                        templateBtn.disabled = false;
                    }, 1500);
                }).catch(() => {});
                return;
            }

            const downloadBtn = event.target.closest('[data-template-download]');
            if (downloadBtn) {
                event.preventDefault();
                const content = downloadBtn.getAttribute('data-template-download') || '';
                const typeName = downloadBtn.getAttribute('data-template-type') || 'catalog';
                downloadText(`${typeName}-import-sample.txt`, content);
                return;
            }

            const reportBtn = event.target.closest('[data-import-download]');
            if (reportBtn) {
                event.preventDefault();
                const mode = reportBtn.getAttribute('data-import-download');
                const resultBox = getResultBox(reportBtn);
                if (!resultBox || !resultBox.__importResult) {
                    return;
                }
                if (mode === 'errors') {
                    downloadText(`catalog-import-errors-${Date.now()}.txt`, buildErrorsText(resultBox.__importResult.errors));
                } else if (mode === 'full') {
                    downloadText(`catalog-import-report-${Date.now()}.txt`, buildFullReport(resultBox.__importResult));
                }
                return;
            }

            const copyBtn = event.target.closest('[data-import-copy="summary"]');
            if (copyBtn) {
                event.preventDefault();
                const resultBox = getResultBox(copyBtn);
                if (!resultBox || !resultBox.__importResult) {
                    return;
                }
                copyText(buildSummaryText(resultBox.__importResult)).then(() => {
                    const original = copyBtn.textContent;
                    copyBtn.textContent = '已複製';
                    copyBtn.disabled = true;
                    setTimeout(() => {
                        copyBtn.textContent = original;
                        copyBtn.disabled = false;
                    }, 1500);
                }).catch(() => {});
            }
        });

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!form.matches('[data-catalog-import-form]')) {
                return;
            }
            event.preventDefault();
            const panel = form.closest('.catalog-import-panel');
            const resultBox = panel ? panel.querySelector('.catalog-import-result') : null;
            const submitBtn = form.querySelector('button[type="submit"]');
            const formData = new FormData(form);

            if (resultBox) {
                resultBox.textContent = '';
                resultBox.classList.remove('error');
                delete resultBox.__importResult;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = '匯入中...';

            fetch('/api/catalog/import-txt.php', {
                method: 'POST',
                body: formData
            })
            .then(async (res) => {
                const raw = await res.text();
                let payload = {};
                try {
                    payload = JSON.parse(raw);
                } catch (error) {
                    throw new Error(res.ok ? '無法解析伺服器回應' : '伺服器錯誤，請稍後再試。');
                }

                if (!res.ok || !payload.success) {
                    throw new Error(resolveErrorMessage(payload.error));
                }

                return payload.data || {};
            })
            .then((summary) => {
                if (resultBox) {
                    renderResult(resultBox, summary);
                }
            })
            .catch((error) => {
                if (resultBox) {
                    resultBox.textContent = error.message || '匯入失敗，請稍後再試。';
                    resultBox.classList.add('error');
                    delete resultBox.__importResult;
                }
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = '開始匯入';
            });
        });
    })();
    </script>
    <?php
}
