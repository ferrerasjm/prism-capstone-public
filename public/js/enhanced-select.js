document.addEventListener('DOMContentLoaded', () => {
    let openPanel = null;

    const closeOpenPanel = () => {
        if (!openPanel) return;
        const { panel, trigger } = openPanel;
        panel.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
        window.removeEventListener('scroll', handleOutsideScroll, true);
        window.removeEventListener('resize', closeOpenPanel);
        openPanel = null;
    };

    const handleOutsideScroll = (event) => {
        if (openPanel && openPanel.panel.contains(event.target)) return;
        closeOpenPanel();
    };

    document.addEventListener('click', (event) => {
        if (!openPanel) return;
        const { panel, trigger } = openPanel;
        if (!panel.contains(event.target) && !trigger.contains(event.target)) {
            closeOpenPanel();
        }
    });

    const positionPanel = (trigger, panel) => {
        const rect = trigger.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        const spaceAbove = rect.top;
        const maxHeight = 260;
        const openUpward = spaceBelow < maxHeight && spaceAbove > spaceBelow;

        panel.style.left = `${rect.left}px`;
        panel.style.width = `${rect.width}px`;
        panel.style.maxHeight = `${Math.min(maxHeight, (openUpward ? spaceAbove : spaceBelow) - 8)}px`;

        if (openUpward) {
            panel.style.bottom = `${window.innerHeight - rect.top + 4}px`;
            panel.style.top = 'auto';
        } else {
            panel.style.top = `${rect.bottom + 4}px`;
            panel.style.bottom = 'auto';
        }
    };

    const enhance = (select) => {
        const wrap = document.createElement('div');
        wrap.className = 'prism-select-wrap';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        select.classList.add('prism-select-native');
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = `prism-select prism-select-trigger ${select.className.replace('prism-select-native', '')}`.trim();
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        wrap.appendChild(trigger);

        const panel = document.createElement('div');
        panel.className = 'prism-select-panel';
        panel.setAttribute('role', 'listbox');
        panel.setAttribute('tabindex', '-1');
        wrap.appendChild(panel);

        const options = [];

        const buildOption = (optionEl, container) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'prism-select-option';
            button.setAttribute('role', 'option');
            button.dataset.value = optionEl.value;
            button.textContent = optionEl.textContent.trim();
            button.addEventListener('click', () => {
                select.value = optionEl.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                refresh();
                closeOpenPanel();
            });
            container.appendChild(button);
            options.push({ el: optionEl, button });
        };

        Array.from(select.children).forEach((child) => {
            if (child.tagName === 'OPTGROUP') {
                const label = document.createElement('div');
                label.className = 'prism-select-group-label';
                label.textContent = child.label;
                panel.appendChild(label);
                Array.from(child.children).forEach((optionEl) => buildOption(optionEl, panel));
            } else if (child.tagName === 'OPTION') {
                if (child.value === '') return;
                buildOption(child, panel);
            }
        });

        const refresh = () => {
            const selected = select.options[select.selectedIndex];
            trigger.textContent = selected ? selected.textContent.trim() : '';
            trigger.classList.toggle('prism-select-placeholder-active', !!selected && selected.value === '');
            options.forEach(({ el, button }) => {
                button.setAttribute('aria-selected', el.value === select.value ? 'true' : 'false');
                button.disabled = el.disabled;
                button.classList.toggle('prism-select-option-disabled', el.disabled);
            });
        };

        trigger.addEventListener('click', () => {
            if (panel.classList.contains('open')) {
                closeOpenPanel();
                return;
            }
            closeOpenPanel();
            refresh();
            positionPanel(trigger, panel);
            panel.classList.add('open');
            trigger.setAttribute('aria-expanded', 'true');
            openPanel = { panel, trigger };
            window.addEventListener('scroll', handleOutsideScroll, true);
            window.addEventListener('resize', closeOpenPanel);
        });

        select.addEventListener('change', refresh);

        refresh();
    };

    document.querySelectorAll('select.prism-select-enhanced').forEach(enhance);
});
