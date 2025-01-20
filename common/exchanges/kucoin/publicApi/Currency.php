<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\kucoin\publicApi;

use KuCoin\SDK\Http\Request;

class Currency extends \KuCoin\SDK\PublicApi\Currency
{
    /**
     * @return array|mixed|null
     * @throws \KuCoin\SDK\Exceptions\BusinessException
     * @throws \KuCoin\SDK\Exceptions\HttpException
     * @throws \KuCoin\SDK\Exceptions\InvalidApiUriException
     */
    public function getList()
    {
        $response = $this->call(Request::METHOD_GET, '/api/v3/currencies');

        return $response->getApiData();
    }
}
