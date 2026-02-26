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
