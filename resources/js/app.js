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
if (contractBeneficiaries && addContractBeneficiary && contractBeneficiaryTemplate && contractItemTemplate) {
    addContractBeneficiary.addEventListener('click', () => {
        const index = Number(contractBeneficiaries.dataset.nextBeneficiary);
        const fragment = contractBeneficiaryTemplate.content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replaceAll('__BEN__', String(index));
        });
        const beneficiaryCard = fragment.querySelector('[data-beneficiary]');
        beneficiaryCard.dataset.beneficiaryIndex = String(index);
        beneficiaryCard.querySelector('h3').textContent = `المستفيد ${index + 1}`;
        contractBeneficiaries.append(fragment);
        contractBeneficiaries.dataset.nextBeneficiary = String(index + 1);
    });
    contractBeneficiaries.addEventListener('click', (event) => {
        const addItem = event.target.closest('[data-add-contract-item]');
        const removeBeneficiary = event.target.closest('[data-remove-beneficiary]');
        if (removeBeneficiary) {
            removeBeneficiary.closest('[data-beneficiary]').remove();
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
    });
}
