<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('.data-table').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'desc']]
    });
    
    $('[data-bs-toggle="tooltip"]').tooltip();
    $('[data-bs-toggle="popover"]').popover();
    
    setTimeout(function() {
        $('.alert:not(.alert-permanent)').fadeTo(500, 0).slideUp(500);
    }, 5000);
});

window.addEventListener('online', function() {
    console.log('Connection restored');
});

window.addEventListener('offline', function() {
    console.log('You are offline');
});
</script>

<?php
while (ob_get_level() > 0) {
    ob_end_flush();
}
?>
</body>
</html>