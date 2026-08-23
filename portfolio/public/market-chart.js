(function () {
    const chartContainer = document.querySelector('[data-market-chart]');
    if (chartContainer) {
        const rawData = JSON.parse(chartContainer.dataset.history || '[]');
        const points = rawData.slice().reverse();

        if (points.length >= 2) {
            renderChart(chartContainer, points);
        }
    }

    function renderChart(container, data) {
        const width = 800;
        const height = 300;
        const padding = { top: 30, right: 40, bottom: 40, left: 70 };

        const minPrice = Math.min(...data.map(d => d.low_pln)) * 0.95;
        const maxPrice = Math.max(...data.map(d => d.high_pln)) * 1.05;
        const priceRange = (maxPrice - minPrice) || 1;

        const getX = index => padding.left + (index / (data.length - 1)) * (width - padding.left - padding.right);
        const getY = price => height - padding.bottom - ((price - minPrice) / priceRange) * (height - padding.top - padding.bottom);

        let areaPath = `M ${getX(0)} ${getY(data[0].high_pln)}`;
        data.forEach((d, i) => { areaPath += ` L ${getX(i)} ${getY(d.high_pln)}`; });
        for (let i = data.length - 1; i >= 0; i--) { areaPath += ` L ${getX(i)} ${getY(d.low_pln)}`; }
        areaPath += ' Z';

        const medianPath = data.map((d, i) => `${i === 0 ? 'M' : 'L'} ${getX(i)} ${getY(d.median_pln)}`).join(' ');

        const gridSteps = 4;
        let gridSvg = '';
        for (let i = 0; i <= gridSteps; i++) {
            const p = minPrice + (i / gridSteps) * priceRange;
            const y = getY(p);
            gridSvg += `
        <line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" stroke="rgba(255,255,255,0.08)" stroke-dasharray="4"/>
        <text x="${padding.left - 12}" y="${y + 4}" fill="var(--muted)" font-size="11" text-anchor="end">${Math.round(p)} zł</text>
      `;
        }

        // Data points and dates
        let pointsSvg = '';
        data.forEach((d, i) => {
            const x = getX(i);
            const y = getY(d.median_pln);
            pointsSvg += `
        <text x="${x}" y="${height - 12}" fill="var(--muted)" font-size="11" text-anchor="middle">${d.date_short}</text>
        <circle cx="${x}" cy="${y}" r="5" fill="var(--orange)" stroke="#07101e" stroke-width="2" class="chart-point" data-idx="${i}"/>
      `;
        });

        container.innerHTML = `
      <div class="chart-wrapper">
        <svg viewBox="0 0 ${width} ${height}" class="price-svg">
          ${gridSvg}
          <path d="${areaPath}" fill="rgba(90, 169, 255, 0.12)" />
          <path d="${medianPath}" fill="none" stroke="var(--orange)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
          ${pointsSvg}
        </svg>
        <div class="chart-tooltip" hidden></div>
      </div>
    `;

        const tooltip = container.querySelector('.chart-tooltip');
        const svg = container.querySelector('.price-svg');
        const circles = container.querySelectorAll('.chart-point');

        circles.forEach(circle => {
            circle.addEventListener('mouseenter', e => {
                const d = data[circle.dataset.idx];
                tooltip.innerHTML = `
          <strong>${d.date_formatted}</strong>
          <div><span>${container.dataset.labelMedian}:</span> <b>${d.median_pln} PLN</b></div>
          <div><span>${container.dataset.labelRange}:</span> ${d.low_pln}–${d.high_pln} PLN</div>
        `;
                tooltip.hidden = false;

                const rect = svg.getBoundingClientRect();
                const cx = (circle.getAttribute('cx') / width) * rect.width;
                const cy = (circle.getAttribute('cy') / height) * rect.height;

                tooltip.style.left = `${cx}px`;
                tooltip.style.top = `${cy - 12}px`;
            });
        });

        container.querySelector('.chart-wrapper').addEventListener('mouseleave', () => {
            tooltip.hidden = true;
        });
    }

    const dealChecker = document.querySelector('[data-deal-checker]');
    if (dealChecker) {
        const input = dealChecker.querySelector('[data-deal-input]');
        const status = dealChecker.querySelector('[data-deal-status]');
        const median = parseFloat(dealChecker.dataset.median);
        const low = parseFloat(dealChecker.dataset.low);
        const high = parseFloat(dealChecker.dataset.high);
        const labels = JSON.parse(dealChecker.dataset.labels || '{}');

        input.addEventListener('input', () => {
            const val = parseFloat(input.value);
            if (!val || isNaN(val) || val <= 0) {
                status.textContent = labels.enterPrice || '';
                status.className = 'deal-status';
                return;
            }

            if (val < low) {
                status.textContent = `🔥 ${labels.greatDeal} (${Math.round((1 - val / median) * 100)}% ${labels.belowMedian})`;
                status.className = 'deal-status deal-great';
            } else if (val <= high) {
                status.textContent = `✅ ${labels.fairDeal} (${labels.withinRange})`;
                status.className = 'deal-status deal-fair';
            } else {
                status.textContent = `⚠️ ${labels.overpriced} (+${Math.round((val / median - 1) * 100)}% ${labels.aboveMedian})`;
                status.className = 'deal-status deal-expensive';
            }
        });
    }

    const specsContainer = document.querySelector('.market-specs[data-sibling-configs]');
    if (specsContainer) {
        const siblings = JSON.parse(specsContainer.dataset.siblingConfigs || '[]');
        const currentSpecs = JSON.parse(specsContainer.dataset.currentSpecs || '{}');
        const selects = specsContainer.querySelectorAll('.market-spec-select');

        selects.forEach(select => {
            select.addEventListener('change', () => {
                const changedKey = select.dataset.specKey;
                const targetSpecs = { ...currentSpecs, [changedKey]: select.value };

                let bestMatch = siblings.find(item => {
                    return Object.entries(targetSpecs).every(([k, v]) => item.specs[k] === v);
                });

                if (!bestMatch) {
                    bestMatch = siblings.find(item => item.specs[changedKey] === select.value) || siblings[0];
                }

                if (bestMatch && bestMatch.url) {
                    window.location.href = bestMatch.url;
                }
            });
        });
    }
})();
