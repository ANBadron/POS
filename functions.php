<?php
function getPaginationLinks($totalItems, $limit, $currentPage, $url) {
    $totalPages = ceil($totalItems / $limit);
    $links = [];

    if ($currentPage > 1) {
        $links[] = "<li class='page-item'><a class='page-link' href='$url?page=" . ($currentPage - 1) . "'>Previous</a></li>";
    }

    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i == $currentPage ? 'active' : '';
        $links[] = "<li class='page-item $active'><a class='page-link' href='$url?page=$i'>$i</a></li>";
    }

    if ($currentPage < $totalPages) {
        $links[] = "<li class='page-item'><a class='page-link' href='$url?page=" . ($currentPage + 1) . "'>Next</a></li>";
    }

    return implode('', $links);
}
?>
