<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\mexc;

class MxcSpot extends \Lin\Mxc\MxcSpot
{

    /**
     * @return Market
     */
    public function market()
    {
        return new Market($this->init());
    }

    /**
     * @return array
     */
    private function init()
    {
        return [
            'key' => $this->key,
            'secret' => $this->secret,
            'passphrase' => $this->passphrase,
            'host' => $this->host,
            'options' => $this->options,
            'platform' => 'spot',
            'version' => 'v2',
        ];
    }
}
