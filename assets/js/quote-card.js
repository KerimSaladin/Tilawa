class QuoteStack {
    constructor(containerId, cards) {
        this.container = document.getElementById(containerId);
        this.cards = cards;
        this.layout = 'stack'; // stack, grid, list
        this.activeIndex = 0;
        this.expandedCardId = null;
        this.resizeDebounce = null;

        // Configuration
        this.SWIPE_THRESHOLD = 50;
        this.ROTATION_DEG = 4;
        this.OFFSET_PX = 8;

        this.init();
    }

    init() {
        this.render();
        this.attachGlobalEvents();
        window.addEventListener('resize', () => {
            clearTimeout(this.resizeDebounce);
            this.resizeDebounce = setTimeout(() => this.render(), 100);
        });
    }

    setLayout(newLayout) {
        if (this.layout === newLayout) return;
        this.layout = newLayout;
        this.render();
        this.updateLayoutButtons();
    }

    setActiveIndex(index) {
        this.activeIndex = index;
        this.render();
    }

    nextCard() {
        this.setActiveIndex((this.activeIndex + 1) % this.cards.length);
    }

    prevCard() {
        this.setActiveIndex((this.activeIndex - 1 + this.cards.length) % this.cards.length);
    }

    getStackOrder() {
        const reordered = [];
        for (let i = 0; i < this.cards.length; i++) {
            const index = (this.activeIndex + i) % this.cards.length;
            reordered.push({ ...this.cards[index], originalIndex: index, stackPosition: i });
        }
        return reordered.reverse();
    }

    createCardElement(card, index, isTopCard) {
        const el = document.createElement('div');
        el.className = `quote-card ${isTopCard && this.layout === 'stack' ? 'top-card' : ''}`;
        el.dataset.id = card.id;

        // Styles based on layout
        if (this.layout === 'stack') {
            const stackPos = card.stackPosition;
            const zIndex = this.cards.length - stackPos;
            const top = stackPos * this.OFFSET_PX; // Positive to stack downwards
            const scale = 1 - (stackPos * 0.05);
            const rotate = (stackPos - 1) * 2;

            // Stacked look
            el.style.zIndex = zIndex;
            el.style.transform = `translate(-50%, -50%) translateY(${top}px) scale(${scale}) rotate(${rotate}deg)`;

            if (isTopCard) {
                this.attachDragEvents(el);
            }
        } else {
            el.style.zIndex = 1;
            el.style.transform = 'none';
        }

        if (card.color) {
            el.style.backgroundColor = card.color;
        }

        // Expanded state
        if (this.expandedCardId === card.id) {
            el.classList.add('expanded');
        }

        el.innerHTML = `
            <div class="card-content">
                ${card.icon ? `<div class="card-icon">${card.icon}</div>` : ''}
                <div class="card-text-wrapper">
                    <h3 class="card-title">${card.title}</h3>
                    <div class="card-description">${card.description}</div>
                </div>
            </div>
            ${isTopCard && this.layout === 'stack' ?
                `<div class="swipe-hint">اسحب للتنقل</div>` : ''}
        `;

        el.addEventListener('click', (e) => {
            if (this.isDragging) return;
            if (this.layout === 'stack' && !isTopCard) return;

            // Toggle expansion logic could go here, but for now we just log
            // this.toggleExpand(card.id);
        });

        return el;
    }

    toggleExpand(cardId) {
        this.expandedCardId = this.expandedCardId === cardId ? null : cardId;
        const overlay = document.querySelector('.overlay');
        if (this.expandedCardId) {
            overlay.classList.add('active');
        } else {
            overlay.classList.remove('active');
        }
        this.render();
    }

    attachDragEvents(el) {
        let startX = 0;
        let currentX = 0;
        let isDragging = false;
        // Top card always has stackPos 0 -> rotate -2deg
        const initialRotate = -2;

        const onStart = (e) => {
            if (this.expandedCardId) return;
            isDragging = true;
            this.isDragging = false; // Flag to distinguish click vs drag
            startX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
            el.style.transition = 'none';
        }

        const onMove = (e) => {
            if (!isDragging) return;
            const x = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
            const diff = x - startX;
            currentX = diff;

            if (Math.abs(diff) > 5) this.isDragging = true;

            // Rotation and movement
            // Add diff rotation to initial rotation
            const rotate = initialRotate + (diff * 0.05);
            el.style.transform = `translate(calc(-50% + ${diff}px), -50%) rotate(${rotate}deg)`;
            el.style.cursor = 'grabbing';
        }

        const onEnd = () => {
            if (!isDragging) return;
            isDragging = false;
            el.style.transition = 'all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
            el.style.cursor = 'grab';

            if (Math.abs(currentX) > this.SWIPE_THRESHOLD) {
                // Swipe detected
                const direction = currentX > 0 ? -1 : 1; // Left swipe (positive dir) -> next, Right swipe -> prev

                // Animate out
                const endX = direction === 1 ? -window.innerWidth : window.innerWidth;
                el.style.transform = `translate(calc(-50% + ${endX}px), -50%) rotate(${direction * 45}deg)`;

                setTimeout(() => {
                    if (direction === 1) this.nextCard();
                    else this.prevCard();
                }, 200);
            } else {
                // Return to center
                el.style.transform = `translate(-50%, -50%) rotate(-2deg)`;
            }
            currentX = 0;
        }

        el.addEventListener('mousedown', onStart);
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onEnd);

        el.addEventListener('touchstart', onStart, { passive: true });
        window.addEventListener('touchmove', onMove, { passive: true });
        window.addEventListener('touchend', onEnd);
    }

    render() {
        const wrapper = this.container.querySelector('.cards-wrapper');
        const navDots = this.container.querySelector('.stack-nav');

        wrapper.innerHTML = '';
        wrapper.className = `cards-wrapper ${this.layout}`;

        let displayCards = [];

        if (this.layout === 'stack') {
            // For stack, we reverse the order so the first item in array is rendered LAST (on top)
            // But getStackOrder already handles the logical logic. 
            // We want the active card to be on top.
            // getStackOrder returns [ ..., activeCard ] so activeCard is last.
            displayCards = this.getStackOrder();
            navDots.style.display = 'flex';
        } else {
            displayCards = this.cards.map((c, i) => ({ ...c, stackPosition: i }));
            navDots.style.display = 'none';
        }

        displayCards.forEach((card, index) => {
            // isTopCard calculation:
            // In stack mode, the last element in displayCards array is the top one visually because of DOM order
            const isTopCard = index === displayCards.length - 1;
            const el = this.createCardElement(card, index, isTopCard);
            wrapper.appendChild(el);
        });

        // Update nav dots
        if (this.layout === 'stack') {
            navDots.innerHTML = '';
            this.cards.forEach((_, idx) => {
                const dot = document.createElement('button');
                dot.className = `nav-dot ${idx === this.activeIndex ? 'active' : ''}`;
                dot.onclick = () => this.setActiveIndex(idx);
                navDots.appendChild(dot);
            });
        }
    }

    updateLayoutButtons() {
        const buttons = this.container.querySelectorAll('.layout-btn');
        buttons.forEach(btn => {
            if (btn.dataset.layout === this.layout) btn.classList.add('active');
            else btn.classList.remove('active');
        });
    }

    attachGlobalEvents() {
        // Layout buttons
        const buttons = this.container.querySelectorAll('.layout-btn');
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                this.setLayout(btn.dataset.layout);
            });
        });

        // Overlay click
        const overlay = document.querySelector('.overlay');
        overlay.onclick = () => {
            this.expandedCardId = null;
            overlay.classList.remove('active');
            this.render();
        }
    }
}
