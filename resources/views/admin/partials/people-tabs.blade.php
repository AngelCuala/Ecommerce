{{-- Tabs over the account pages (sidebar: Accounts). --}}
@include('partials.page-tabs', ['tabs' => [
    ['Buyers', 'admin.customers.index', 'admin.customers.*'],
    ['Sellers', 'admin.sellers.index', 'admin.sellers.*'],
    ['Riders', 'admin.riders.index', 'admin.riders.*'],
    ['Sorting centers', 'admin.sorting-centers.index', 'admin.sorting-centers.*'],
    ['All accounts', 'admin.users.index', 'admin.users.*'],
]])
