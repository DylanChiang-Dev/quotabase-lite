(function() {
    const roots = document.querySelectorAll('[data-catalog-bulk-root]');
    if (!roots.length) {
        return;
    }

    roots.forEach((root) => {
        const endpoint = root.getAttribute('data-bulk-endpoint');
        const type = root.getAttribute('data-bulk-type') || 'product';
        const tokenInput = root.querySelector('[data-catalog-bulk-token]');
        const deleteButton = root.querySelector('[data-bulk-delete-btn]');
        const indicator = root.querySelector('[data-selected-count]');
        const selectAll = root.querySelector('[data-catalog-select-all]');

        if (!endpoint || !tokenInput || !deleteButton) {
            return;
        }

        const getCheckboxes = () => Array.from(root.querySelectorAll('[data-catalog-select]'));

        const setIndicator = (count) => {
            if (!indicator) {
                return;
            }
            indicator.textContent = `已選擇 ${count} 項`;
            if (count > 0) {
                indicator.removeAttribute('hidden');
            } else {
                indicator.setAttribute('hidden', 'hidden');
            }
        };

        const refreshState = () => {
            const checkboxes = getCheckboxes();
            const selected = checkboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value);
            deleteButton.disabled = selected.length === 0;
            setIndicator(selected.length);

            if (selectAll) {
                const total = checkboxes.length;
                const checkedCount = selected.length;
                selectAll.checked = total > 0 && checkedCount === total;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < total;
            }

            return selected;
        };

        getCheckboxes().forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                refreshState();
            });
        });

        if (selectAll) {
            selectAll.addEventListener('change', () => {
                const checkboxes = getCheckboxes();
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });
                refreshState();
            });
        }

        deleteButton.addEventListener('click', async () => {
            const selectedIds = refreshState();
            if (!selectedIds.length) {
                return;
            }

            const label = type === 'service' ? '服務' : '產品';
            const confirmed = window.confirm(`確認刪除 ${selectedIds.length} 筆${label}？`);
            if (!confirmed) {
                return;
            }

            deleteButton.disabled = true;
            deleteButton.dataset.loading = 'true';

            const formData = new FormData();
            formData.append('csrf_token', tokenInput.value);
            formData.append('type', type);
            selectedIds.forEach((id) => formData.append('ids[]', id));

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const payload = await response.json().catch(() => null);
                const result = payload && payload.data ? payload.data : null;
                const url = new URL(window.location.href);

                if (response.ok && result) {
                    url.searchParams.set('success', result.message || '批量刪除完成');
                    url.searchParams.delete('error');
                } else {
                    const message = (result && result.message) || (payload && payload.error) || '批量刪除失敗，請稍後再試';
                    url.searchParams.set('error', message);
                    url.searchParams.delete('success');
                }

                window.location.href = url.toString();
            } catch (error) {
                console.error('[CatalogBulkDelete] 發送請求失敗', error);
                alert('批量刪除失敗，請檢查網路或稍後再試。');
                deleteButton.disabled = false;
                deleteButton.dataset.loading = 'false';
            }
        });

        refreshState();
    });
})();
