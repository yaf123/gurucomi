<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\GurucomiBaseSearchService;

use Mockery;
use Tests\CreatesApplication;

class GurucomiBaseSearchServiceTest extends TestCase
{

    use CreatesApplication;

    /** @var GurucomiBaseSearchService */
    protected $GurucomiBaseSearchService;

    public function setUp(): void
    {
        parent::setUp();

        $this->createApplication();

        // GurucomiBaseSearchServiceインスタンス生成
        $this->GurucomiBaseSearchService = app(GurucomiBaseSearchService::class);

    }

/**
     * @test
     * @group unit
     * @group service
     * @group getGurucomibasesrnd
     */
    public function getGurucomibasesrnd_返り値のデータチェック()
    {
        $data = $this->GurucomiBaseSearchService->getGurucomibasesrnd();
        //データチェック
        $this->assertArrayHasKey('ggcomi_base_id', $data[0]);
    }
}
