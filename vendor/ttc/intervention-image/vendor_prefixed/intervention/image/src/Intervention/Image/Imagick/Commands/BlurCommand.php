<?php
/* This file has been prefixed by <PHP-Prefixer> for "Responsive Images" */

namespace Ttc\Intervention\Image\Imagick\Commands;

use Ttc\Intervention\Image\Commands\AbstractCommand;

class BlurCommand extends AbstractCommand
{
    /**
     * Applies blur effect on image
     *
     * @param  \Intervention\Image\Image $image
     * @return boolean
     */
    public function execute($image)
    {
        $amount = $this->argument(0)->between(0, 100)->value(1);

        return $image->getCore()->blurImage(1 * $amount, 0.5 * $amount);
    }
}
