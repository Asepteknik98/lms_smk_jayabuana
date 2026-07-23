<style>
    .app-global-footer {
        margin-top: auto;
        padding: 18px 20px;
        border-top: 1px solid #e5e7eb;
        background: #fff;
        color: #64748b;
        font-size: .82rem;
        text-align: center;
        letter-spacing: .01em;
    }
    #page-content-wrapper {
        display: flex;
        flex-direction: column;
    }
    #page-content-wrapper > .app-global-footer {
        width: 100%;
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const page = document.getElementById('page-content-wrapper');
    if (page && !page.querySelector('.app-global-footer')) {
        const footer = document.createElement('footer');
        footer.className = 'app-global-footer';
        footer.innerHTML = '&copy; Copyright SMKS JAYA BUANA 2026 <strong>V.2.1</strong>';
        page.appendChild(footer);
    }
});
</script>
</div> <!-- End #wrapper -->

<!-- jQuery, Bootstrap, DataTables, Chart.js & SweetAlert2 JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if(!empty($needs_datatables)): ?><script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script><?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
