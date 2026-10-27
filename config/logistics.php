<?php

return [
    /*
     * Hours a dispatched hub-to-hub transfer may take before it is flagged as delayed
     * (an exception for investigation, and the buyer / seller are told). LOGISTICS_TRANSFER_HOURS.
     */
    'transfer_receipt_hours' => (int) env('LOGISTICS_TRANSFER_HOURS', 24),
];
