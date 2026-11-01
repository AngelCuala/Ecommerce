<?php

namespace App\Http\Controllers;

use App\Support\ProtectedFile;

/** Shows a protected upload (ID, permit, proof of delivery, …) to whoever holds a valid signed link (ProtectedFile::url). */
class ProtectedFileController extends Controller
{
    public function __invoke(string $path)
    {
        abort_unless(ProtectedFile::isProtected($path), 404);   // only the protected folders, never anything else

        return ProtectedFile::response($path);
    }
}
