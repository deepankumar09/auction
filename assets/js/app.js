document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (event) => {
        if (!confirm(el.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    });
});

function formatTimeLeft(totalSeconds) {
    if (totalSeconds <= 0) {
        return 'Auction Closed';
    }

    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    if (days > 0) {
        return `${days}d ${hours}h ${minutes}m ${seconds}s`;
    }
    return `${hours}h ${minutes}m ${seconds}s`;
}

function updateAuctionCountdowns() {
    const countdownEls = document.querySelectorAll('[data-countdown-end]');
    if (!countdownEls.length) {
        return;
    }

    const now = Math.floor(Date.now() / 1000);
    countdownEls.forEach((el) => {
        const end = parseInt(el.getAttribute('data-countdown-end') || '0', 10);
        const remaining = end - now;
        const formatted = formatTimeLeft(remaining);

        if (el.classList.contains('auction-countdown')) {
            el.textContent = `Time Left: ${formatted}`;
        } else {
            el.textContent = formatted;
        }
    });
}

updateAuctionCountdowns();
setInterval(updateAuctionCountdowns, 1000);

const flashToasts = document.querySelectorAll('[data-flash-toast]');
flashToasts.forEach((toast) => {
    const delayMs = 3000;
    window.setTimeout(() => {
        toast.classList.add('is-hiding');
        window.setTimeout(() => {
            toast.remove();
        }, 320);
    }, delayMs);
});

function initAddVehicleBasePriceAutoCalc() {
    const marketValueInput = document.getElementById('market_value');
    const conditionInput = document.getElementById('vehicle_condition');
    const basePriceInput = document.getElementById('base_price');
    if (!marketValueInput || !conditionInput || !basePriceInput) {
        return;
    }

    const rates = {
        Good: 0.85,
        Average: 0.70,
        Damaged: 0.55,
    };

    const updateBasePrice = () => {
        const marketValue = parseFloat(marketValueInput.value || '0');
        const condition = conditionInput.value || '';
        const rate = rates[condition];

        if (!rate || marketValue <= 0) {
            basePriceInput.value = '';
            return;
        }

        basePriceInput.value = (marketValue * rate).toFixed(2);
    };

    marketValueInput.addEventListener('input', updateBasePrice);
    conditionInput.addEventListener('change', updateBasePrice);
    updateBasePrice();
}

initAddVehicleBasePriceAutoCalc();
