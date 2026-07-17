<?php

namespace App\Services\Communication\Channels;

use App\Models\Communication;

interface CommunicationChannel
{
    /**
     * Dispatch the communication. Throw on failure — the caller is
     * responsible for catching and recording the failure reason.
     */
    public function send(Communication $communication): void;
}
