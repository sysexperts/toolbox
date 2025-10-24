document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('invoice-form');
    if (!form) {
        return;
    }

    const itemsTableBody = document.querySelector('#invoice-items tbody');
    const addItemButton = document.getElementById('add-item');
    const subtotalField = document.getElementById('summary-subtotal');
    const taxField = document.getElementById('summary-tax');
    const totalField = document.getElementById('summary-total');
    const taxRateInput = document.querySelector('input[name="tax_rate"]');
    const taxRateLabel = document.getElementById('summary-tax-rate');
    const currencySelect = form.querySelector('[name="currency"]');

    const getCurrency = () => (currencySelect ? currencySelect.value || 'EUR' : 'EUR');

    const formatCurrency = (value) =>
        new Intl.NumberFormat('de-DE', { style: 'currency', currency: getCurrency() }).format(value || 0);

    const recalc = () => {
        const rows = itemsTableBody.querySelectorAll('tr');
        let subtotal = 0;

        rows.forEach((row) => {
            const quantityInput = row.querySelector('input[name="item_quantity[]"]');
            const unitPriceInput = row.querySelector('input[name="item_unit_price[]"]');
            const lineTotalCell = row.querySelector('.item-total');

            const quantity = parseFloat((quantityInput.value || '0').replace(',', '.')) || 0;
            const unitPrice = parseFloat((unitPriceInput.value || '0').replace(',', '.')) || 0;
            const lineTotal = Math.max(quantity * unitPrice, 0);
            subtotal += lineTotal;

            lineTotalCell.textContent = formatCurrency(lineTotal);
        });

        const taxRate = parseFloat((taxRateInput && taxRateInput.value) || '0') || 0;
        const taxTotal = subtotal * (taxRate / 100);
        const total = subtotal + taxTotal;

        subtotalField.textContent = formatCurrency(subtotal);
        taxField.textContent = formatCurrency(taxTotal);
        totalField.textContent = formatCurrency(total);
        taxRateLabel.textContent = taxRate.toString();
    };

    const attachRowEvents = (row) => {
        row.querySelectorAll('input').forEach((input) => input.addEventListener('input', recalc));

        const removeButton = row.querySelector('.remove-row');
        if (removeButton) {
            removeButton.addEventListener('click', () => {
                if (itemsTableBody.querySelectorAll('tr').length <= 1) {
                    return;
                }
                row.remove();
                recalc();
            });
        }
    };

    addItemButton.addEventListener('click', () => {
        const newRow = document.createElement('tr');
        newRow.innerHTML = [
            '<td><input type="text" name="item_description[]" placeholder="Leistung oder Produkt"></td>',
            '<td><input type="number" min="0" step="0.01" name="item_quantity[]" value="1"></td>',
            '<td><input type="number" min="0" step="0.01" name="item_unit_price[]" value="0"></td>',
            `<td class="item-total">${formatCurrency(0)}</td>`,
            '<td><button type="button" class="link-button link-button--danger remove-row">Entfernen</button></td>',
        ].join('');

        itemsTableBody.appendChild(newRow);
        attachRowEvents(newRow);
        recalc();
    });

    itemsTableBody.querySelectorAll('tr').forEach(attachRowEvents);
    if (taxRateInput) {
        taxRateInput.addEventListener('input', recalc);
    }
    if (currencySelect) {
        currencySelect.addEventListener('change', recalc);
    }

    recalc();
});
