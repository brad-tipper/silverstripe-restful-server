<?php

namespace BradTipper\RestfulServer\Api;

/**
 * Marks a controller whose configured actions may require bearer auth.
 * Authentication remains opt-in through $authenticated_actions.
 */
interface SupportsAuth
{
}
