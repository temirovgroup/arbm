<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\mexc;

class Market extends \Lin\Mxc\Api\Spot\Market
{
    /**
     *GET /open/api/v2/market/coin/list
     * */
    public function getList(array $data = [])
    {
        $this->type = 'GET';
        $this->path = '/open/api/v2/market/coin/list';
        $this->data = $data;

        return $this->exec();
    }
}
