/**
 * Tilt Effect - Vanilla JavaScript implementation
 * Converts React/Framer Motion tilt effect to vanilla JS
 */

class TiltEffect {
    constructor(element, options = {}) {
        this.element = typeof element === 'string' ? document.querySelector(element) : element;
        if (!this.element) return;
        
        this.rotationFactor = options.rotationFactor || 15;
        this.isReverse = options.isReverse || false;
        this.springTension = options.springTension || 300;
        this.springDamping = options.springDamping || 30;
        
        this.x = 0;
        this.y = 0;
        this.targetX = 0;
        this.targetY = 0;
        this.velocityX = 0;
        this.velocityY = 0;
        
        this.rotateX = 0;
        this.rotateY = 0;
        
        this.animationFrame = null;
        this.isAnimating = false;
        
        this.init();
    }
    
    init() {
        // Set transform style
        this.element.style.transformStyle = 'preserve-3d';
        this.element.style.willChange = 'transform';
        
        // Add event listeners
        this.element.addEventListener('mousemove', (e) => this.handleMouseMove(e));
        this.element.addEventListener('mouseleave', () => this.handleMouseLeave());
        
        // Start animation loop
        this.animate();
    }
    
    handleMouseMove(e) {
        const rect = this.element.getBoundingClientRect();
        const width = rect.width;
        const height = rect.height;
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;
        
        // Calculate position relative to center (-0.5 to 0.5)
        this.targetX = (mouseX / width) - 0.5;
        this.targetY = (mouseY / height) - 0.5;
    }
    
    handleMouseLeave() {
        this.targetX = 0;
        this.targetY = 0;
    }
    
    springUpdate(current, target, velocity, tension, damping) {
        const delta = target - current;
        const springForce = delta * (tension / 10000);
        const dampingForce = velocity * (damping / 1000);
        const acceleration = springForce - dampingForce;
        
        velocity += acceleration;
        const newCurrent = current + velocity;
        
        // Stop if very close to target
        if (Math.abs(delta) < 0.001 && Math.abs(velocity) < 0.001) {
            return { value: target, velocity: 0 };
        }
        
        return { value: newCurrent, velocity };
    }
    
    animate() {
        // Update spring physics
        const xUpdate = this.springUpdate(
            this.x,
            this.targetX,
            this.velocityX,
            this.springTension,
            this.springDamping
        );
        this.x = xUpdate.value;
        this.velocityX = xUpdate.velocity;
        
        const yUpdate = this.springUpdate(
            this.y,
            this.targetY,
            this.velocityY,
            this.springTension,
            this.springDamping
        );
        this.y = yUpdate.value;
        this.velocityY = yUpdate.velocity;
        
        // Calculate rotation
        if (this.isReverse) {
            this.rotateX = this.y * this.rotationFactor;
            this.rotateY = -this.x * this.rotationFactor;
        } else {
            this.rotateX = -this.y * this.rotationFactor;
            this.rotateY = this.x * this.rotationFactor;
        }
        
        // Apply transform
        this.element.style.transform = 
            `perspective(1000px) rotateX(${this.rotateX}deg) rotateY(${this.rotateY}deg)`;
        
        // Continue animation
        this.animationFrame = requestAnimationFrame(() => this.animate());
    }
    
    destroy() {
        if (this.animationFrame) {
            cancelAnimationFrame(this.animationFrame);
        }
        this.element.removeEventListener('mousemove', this.handleMouseMove);
        this.element.removeEventListener('mouseleave', this.handleMouseLeave);
    }
}

// Auto-initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tilt effect on elements with data-tilt attribute
    document.querySelectorAll('[data-tilt]').forEach(element => {
        const rotationFactor = element.dataset.tiltRotation 
            ? parseFloat(element.dataset.tiltRotation) 
            : 15;
        const isReverse = element.dataset.tiltReverse === 'true';
        
        new TiltEffect(element, {
            rotationFactor: rotationFactor,
            isReverse: isReverse
        });
    });
});

