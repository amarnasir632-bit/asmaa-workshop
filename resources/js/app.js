const addItemButton = document.querySelector('#add-order-item');
const itemContainer = document.querySelector('#order-items');
const itemTemplate = document.querySelector('#order-item-template');

if (addItemButton && itemContainer && itemTemplate) {
    addItemButton.addEventListener('click', () => {
        const index = Number(itemContainer.dataset.nextIndex);
        const fragment = itemTemplate.content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replaceAll('__INDEX__', String(index));
        });
        fragment.querySelector('[data-item] h3').textContent = `البند ${index + 1}`;
        itemContainer.append(fragment);
        itemContainer.dataset.nextIndex = String(index + 1);
        updateReadyVariantRequirements();
    });

    itemContainer.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-item]');
        if (!removeButton) return;
        const items = itemContainer.querySelectorAll('[data-item]');
        if (items.length <= 1) return;
        removeButton.closest('[data-item]').remove();
    });
}

const orderTypeSelect = document.querySelector('#order-type');
function updateReadyVariantRequirements() {
    const required = orderTypeSelect?.value === 'ready';
    document.querySelectorAll('[data-ready-stock-select]').forEach((select) => {
        select.required = required;
    });
}
if (orderTypeSelect) {
    orderTypeSelect.addEventListener('change', updateReadyVariantRequirements);
    updateReadyVariantRequirements();
}

for (const section of ['opening', 'adjustment']) {
    const button = document.querySelector(`[data-add-row="${section}"]`);
    const container = document.querySelector(`#${section}-entries`);
    const template = document.querySelector(`#${section}-template`);
    if (!button || !container || !template) continue;
    button.addEventListener('click', () => {
        const index = Number(container.dataset.nextIndex);
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replaceAll('__INDEX__', String(index));
        });
        container.append(fragment);
        container.dataset.nextIndex = String(index + 1);
    });
}

const contractBeneficiaries = document.querySelector('#contract-beneficiaries');
const addContractBeneficiary = document.querySelector('#add-contract-beneficiary');
const contractBeneficiaryTemplate = document.querySelector('#contract-beneficiary-template');
const contractItemTemplate = document.querySelector('#contract-item-template');
const schoolSelect = document.querySelector('#school-select');

function updateContractSummary() {
    const summaryCard = document.querySelector('#school-order-summary');
    if (!summaryCard) return;

    let totalGarments = 0;
    let grossTotal = 0;
    const selectedGrades = new Set();

    document.querySelectorAll('[data-beneficiary]').forEach((card) => {
        const gradeSelect = card.querySelector('.grade-select');
        if (gradeSelect && gradeSelect.value) {
            selectedGrades.add(gradeSelect.value);
        }

        card.querySelectorAll('[data-contract-item-row]').forEach((row) => {
            const qtyInput = row.querySelector('.item-quantity');
            const priceInput = row.querySelector('.item-unit-price');
            const discountInput = row.querySelector('.item-discount');

            const qty = Number(qtyInput?.value || 0);
            const price = Number(priceInput?.value || 0);
            const discount = Number(discountInput?.value || 0);

            if (qty > 0) {
                totalGarments += qty;
                const lineTotal = Math.max(0, (qty * price) - discount);
                grossTotal += lineTotal;
            }
        });
    });

    const paymentInput = document.querySelector('#initial-payment-amount');
    const paidAmount = Math.max(0, Number(paymentInput?.value || 0));
    const dueAmount = Math.max(0, grossTotal - paidAmount);

    const fmt = (num) => Number(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const totalGarmentsEl = document.querySelector('#summary-total-garments');
    const totalGradesEl = document.querySelector('#summary-total-grades');
    const grossTotalEl = document.querySelector('#summary-gross-total');
    const paidAmountEl = document.querySelector('#summary-paid-amount');
    const dueAmountEl = document.querySelector('#summary-due-amount');

    if (totalGarmentsEl) totalGarmentsEl.textContent = totalGarments;
    if (totalGradesEl) totalGradesEl.textContent = selectedGrades.size;
    if (grossTotalEl) grossTotalEl.textContent = fmt(grossTotal);
    if (paidAmountEl) paidAmountEl.textContent = fmt(paidAmount);
    if (dueAmountEl) dueAmountEl.textContent = fmt(dueAmount);
}

function filterGradesBySelectedSchool() {
    if (!schoolSelect) return;
    const selectedSchoolId = schoolSelect.value;

    document.querySelectorAll('.grade-select').forEach((select) => {
        Array.from(select.options).forEach((opt) => {
            if (!opt.value) return;
            const schoolId = opt.dataset.schoolId;
            if (!selectedSchoolId || schoolId === selectedSchoolId) {
                opt.hidden = false;
                opt.disabled = false;
            } else {
                opt.hidden = true;
                opt.disabled = true;
                if (select.value === opt.value) {
                    select.value = '';
                }
            }
        });
    });
}

if (schoolSelect) {
    schoolSelect.addEventListener('change', () => {
        filterGradesBySelectedSchool();
        updateContractSummary();
    });
    filterGradesBySelectedSchool();
}

const contractForm = document.querySelector('form[action*="school-contracts"]');
if (contractForm) {
    contractForm.addEventListener('input', updateContractSummary);
    contractForm.addEventListener('change', updateContractSummary);
    updateContractSummary();
}

if (contractBeneficiaries && addContractBeneficiary && contractBeneficiaryTemplate && contractItemTemplate) {
    addContractBeneficiary.addEventListener('click', () => {
        const index = Number(contractBeneficiaries.dataset.nextBeneficiary);
        const fragment = contractBeneficiaryTemplate.content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replaceAll('__BEN__', String(index));
        });
        const beneficiaryCard = fragment.querySelector('[data-beneficiary]');
        beneficiaryCard.dataset.beneficiaryIndex = String(index);
        beneficiaryCard.querySelector('h3').textContent = `المرحلة / الدفعة ${index + 1}`;
        contractBeneficiaries.append(fragment);
        contractBeneficiaries.dataset.nextBeneficiary = String(index + 1);
        filterGradesBySelectedSchool();
        updateContractSummary();
    });
    contractBeneficiaries.addEventListener('click', (event) => {
        const addItem = event.target.closest('[data-add-contract-item]');
        const removeBeneficiary = event.target.closest('[data-remove-beneficiary]');
        if (removeBeneficiary) {
            removeBeneficiary.closest('[data-beneficiary]').remove();
            updateContractSummary();
            return;
        }
        if (!addItem) return;
        const beneficiary = addItem.closest('[data-beneficiary]');
        const beneficiaryIndex = beneficiary.dataset.beneficiaryIndex;
        const itemIndex = Number(beneficiary.dataset.nextItem);
        const fragment = contractItemTemplate.content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replaceAll('__BEN__', beneficiaryIndex).replaceAll('__ITEM__', String(itemIndex));
        });
        beneficiary.querySelector('[data-contract-items]').append(fragment);
        beneficiary.dataset.nextItem = String(itemIndex + 1);
        updateContractSummary();
    });
}
