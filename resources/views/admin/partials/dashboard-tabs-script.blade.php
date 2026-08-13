<script>
    document.querySelectorAll('.dash-tabs').forEach(tabs => {
        const buttons = tabs.querySelectorAll('.dash-tab');
        buttons.forEach(button => button.addEventListener('click', () => {
            buttons.forEach(other => {
                other.classList.toggle('active', other === button);
                other.setAttribute('aria-selected', String(other === button));
            });

            const target = button.dataset.tab;
            document.querySelectorAll('.dash-panel').forEach(panel => {
                panel.hidden = panel.dataset.panel !== target;
            });

            window.dispatchEvent(new CustomEvent('vati:tab-shown', {
                detail: {
                    tab: target
                }
            }));
        }));
    });
</script>
