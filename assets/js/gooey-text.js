/**
 * Gooey Text Effect - Vanilla JavaScript implementation
 * Creates smooth morphing/blur effect between different text strings
 */

class GooeyText {
    constructor(element, options = {}) {
        this.element = typeof element === 'string' ? document.querySelector(element) : element;
        if (!this.element) return;
        
        this.texts = options.texts || [];
        this.morphTime = options.morphTime || 1;
        this.cooldownTime = options.cooldownTime || 0.25;
        this.textClassName = options.textClassName || '';
        
        this.text1Ref = null;
        this.text2Ref = null;
        this.textIndex = this.texts.length - 1;
        this.time = new Date();
        this.morph = 0;
        this.cooldown = this.cooldownTime;
        this.animationFrame = null;
        
        this.init();
    }
    
    init() {
        // Create or get SVG filter (only once in document)
        let svgFilter = document.getElementById('gooey-filter-svg');
        if (!svgFilter) {
            svgFilter = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svgFilter.setAttribute('id', 'gooey-filter-svg');
            svgFilter.setAttribute('style', 'position: absolute; width: 0; height: 0;');
            svgFilter.setAttribute('aria-hidden', 'true');
            svgFilter.setAttribute('focusable', 'false');
            
            const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
            const filter = document.createElementNS('http://www.w3.org/2000/svg', 'filter');
            filter.setAttribute('id', 'gooey-filter');
            
            const feColorMatrix = document.createElementNS('http://www.w3.org/2000/svg', 'feColorMatrix');
            feColorMatrix.setAttribute('in', 'SourceGraphic');
            feColorMatrix.setAttribute('type', 'matrix');
            feColorMatrix.setAttribute('values', '1 0 0 0 0 0 1 0 0 0 0 0 1 0 0 0 0 0 255 -140');
            
            filter.appendChild(feColorMatrix);
            defs.appendChild(filter);
            svgFilter.appendChild(defs);
            document.body.appendChild(svgFilter);
        }
        
        this.filterId = 'gooey-filter';
        
        // Create container
        const container = document.createElement('div');
        container.className = 'relative';
        if (this.element.className) {
            container.className += ' ' + this.element.className;
        }
        container.style.filter = `url(#${this.filterId})`;
        container.style.display = 'inline-flex';
        container.style.alignItems = 'center';
        container.style.justifyContent = 'flex-end';
        container.style.position = 'relative';
        container.style.minHeight = '1.5em';
        container.style.width = '100%';
        
        // Create text spans
        this.text1Ref = document.createElement('span');
        this.text1Ref.className = `absolute inline-block select-none text-center ${this.textClassName}`;
        this.text1Ref.style.position = 'absolute';
        this.text1Ref.style.opacity = '0%';
        this.text1Ref.style.right = '0';
        this.text1Ref.style.top = '0.5em';
        
        this.text2Ref = document.createElement('span');
        this.text2Ref.className = `absolute inline-block select-none text-center ${this.textClassName}`;
        this.text2Ref.style.position = 'absolute';
        this.text2Ref.style.opacity = '100%';
        this.text2Ref.style.right = '0';
        this.text2Ref.style.top = '0.5em';
        
        // Set initial text
        if (this.texts.length > 0) {
            this.text1Ref.textContent = this.texts[this.textIndex % this.texts.length];
            this.text2Ref.textContent = this.texts[(this.textIndex + 1) % this.texts.length];
        }
        
        container.appendChild(this.text1Ref);
        container.appendChild(this.text2Ref);
        
        // Replace original element content
        this.element.innerHTML = '';
        this.element.appendChild(container);
        this.container = container;
        
        // Start animation
        this.animate();
    }
    
    setMorph(fraction) {
        if (!this.text1Ref || !this.text2Ref) return;
        
        this.text2Ref.style.filter = `blur(${Math.min(8 / fraction - 8, 100)}px)`;
        this.text2Ref.style.opacity = `${Math.pow(fraction, 0.4) * 100}%`;
        
        fraction = 1 - fraction;
        this.text1Ref.style.filter = `blur(${Math.min(8 / fraction - 8, 100)}px)`;
        this.text1Ref.style.opacity = `${Math.pow(fraction, 0.4) * 100}%`;
    }
    
    doCooldown() {
        this.morph = 0;
        if (this.text1Ref && this.text2Ref) {
            this.text2Ref.style.filter = '';
            this.text2Ref.style.opacity = '100%';
            this.text1Ref.style.filter = '';
            this.text1Ref.style.opacity = '0%';
        }
    }
    
    doMorph() {
        this.morph -= this.cooldown;
        this.cooldown = 0;
        let fraction = this.morph / this.morphTime;
        
        if (fraction > 1) {
            this.cooldown = this.cooldownTime;
            fraction = 1;
        }
        
        this.setMorph(fraction);
    }
    
    animate() {
        const newTime = new Date();
        const shouldIncrementIndex = this.cooldown > 0;
        const dt = (newTime.getTime() - this.time.getTime()) / 1000;
        this.time = newTime;
        
        this.cooldown -= dt;
        
        if (this.cooldown <= 0) {
            if (shouldIncrementIndex) {
                this.textIndex = (this.textIndex + 1) % this.texts.length;
                if (this.text1Ref && this.text2Ref) {
                    this.text1Ref.textContent = this.texts[this.textIndex % this.texts.length];
                    this.text2Ref.textContent = this.texts[(this.textIndex + 1) % this.texts.length];
                }
            }
            this.doMorph();
        } else {
            this.doCooldown();
        }
        
        this.animationFrame = requestAnimationFrame(() => this.animate());
    }
    
    destroy() {
        if (this.animationFrame) {
            cancelAnimationFrame(this.animationFrame);
        }
    }
}

// Auto-initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Find all elements with data-gooey-text attribute
    document.querySelectorAll('[data-gooey-text]').forEach(element => {
        const texts = element.dataset.gooeyText.split('|');
        const morphTime = parseFloat(element.dataset.morphTime) || 1;
        const cooldownTime = parseFloat(element.dataset.cooldownTime) || 0.25;
        const textClassName = element.dataset.textClassName || '';
        
        new GooeyText(element, {
            texts: texts,
            morphTime: morphTime,
            cooldownTime: cooldownTime,
            textClassName: textClassName
        });
    });
});

