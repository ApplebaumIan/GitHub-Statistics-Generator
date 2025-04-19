<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_mermaid_url_redirected(): void
    {
        $response = $this->get('/api/pull-requests/Capstone-Projects-2025-Spring/sample-project');
//        $response->assertStatus(301)->assertRedirect('https://mermaid.ink/img/pako:eNpFkE1ugzAQha9izRoi8xfAi0hVuumiF2icxQADcYVtZIxEGiH1ED1hT1IS1HZW8_S9N0-aG9S2IRAwX-sLOh9W5FEato5Xvicm4Wi1Vn5k359fbEQ99BQOzr5T7SVszjnEWY3sJOFpWHGFk35BI-G84euG_y5JYJyF4YFFG6_QsVN0hgA6pxoQ3k0UgCan8S7hdrdJ8BfSJEGsa0MtTv2jf1ljA5o3a_Vv0tmpu4BosR9XNQ0NenpW2Dn8t5BpyB3tZDyIKOGPGyBuMK8yi3dlVuwjXqZFxJM8gCuIOE13Wc7jhOdlWWacF0sAH49Wvst5Usb7pIjTKOdFmgZAjfLWvW6_ra1pVQfLD55-cSE');
        $response->assertStatus(301)->assertRedirect('https://mermaid.ink/img/eyJjb2RlIjoieHljaGFydC1iZXRhXG50aXRsZSBcIkNvbW1pdHMg4oCUIEV4YW1wbGVcIlxueC1heGlzIFtcIkFwcGxlYmF1bUlhblwiXVxueS1heGlzIFwiQ29tbWl0c1wiIDAgLS0-IDFcbmJhciBbMV0iLCJtZXJtYWlkIjp7InRoZW1lIjoiZGVmYXVsdCJ9fQ');
        //        $response = $this->get('/api/pull-requests/Capstone-Projects-2025-Spring/capstone-projects-2025-spring-classroom-95fb36-applebaum-projects-creation-tu-cis-4398-docs-template');
        //        $response->assertStatus(301)->assertRedirect('https://mermaid.ink/img/pako:eNpVVdtu4zYQ_RVBz2ZA3SzLDwUS762psyjSdAt0vQ-MNBYZUaTCS2I5CNCP6Bf2S0qLlOsKfOCcGc7lDDl6i2vZQLyOD2NNiTLoEQzZich9hhkO0S7eyL5nRkf__PV3VJNBGykADUo-QW00SnFaID0oJlpUc6K1krJHVbF_zJaIDAOHR2L7_-xrBcQwKZCxqGYa5Vm1Qo2sNTLQD5wY2MU-_gGRA9PR911cU8Y628pdvHD5XItGsRFtbU27AHUg9lJoqEmQKRH02cpatOkKJx40nML4SlTjxS9EDUw0oD4BcL1lHWxk46rw2lupqSXoG1EdjEnqQdkTpSnpmFp5oIHDUzjwFV5AfXw5uePs4DFnu2fPFU6C-eYGVJtlmZfuiDFoy4xjec-Ah7R6yfmIBjl4UUjgyHWmnku9lWJDQalx9PIN-mLbFlQ4zShTQxIqrjXre8eL61HukW9EM55koZ6vsv7F2HuRbwMlINToCKuCsTbKlRwSIYYSzsgL4Zx5SBkQSXUuVSkpjO1DB54ADMUhrLFymWZVEdKiSo4hhZYTYVgbEmaa9qTH6XxK5zivQoTPVnCpGn2ONwoiGw5ehp464ogILJKOMt4RbawKyX5sbi0Rn6U4Eg7H4EQOlEFNXRmhQ7eOaPTHyBkk2Yy8CjF-CG5pK3nTw0z3tXCPgck7KVrg9sgu3fazz45a7S7w4K42Dz6tNkw8KOvO5cXSg1uiCPoESrgiQKMlLjMc_DkmH50iFOL6ILbupLHPM0-qKvHcCf7kuhZsb-8pwMPvQfh1S6hsQj9Pd06Rc3tSnK8C7Q92CnFjz_ruxgqRz7fqibAeRGtHR9tyrlEqLl90x8Kt_M0Q9_IdOa7XLLTseh4GP5OQHry6Mo6hGaRR8GoUEUlShuvJ6u7e1kw4vx65v5fu7bcWjqlj7YefE6OfE-dBtYsjHCH0U1R6_SNR0fdiEbmVL6J02mSLCE_L7_Npn07L48lsk0ygN8gvtPjCIJlUyYVYXuzxhZ9kDnHGz2aXC__fMvkRL-JWsSZeG2VhEfegenIS47dTja6DFHr3EtZuu5cKtHF8ec1h3Jzm-kn35vnYxa-sMfSEVAVezCAF1tLJLsf4BL7vxLuLOxDxpxvpc2glbUvj9Z5w7SQ7NO5af2CkVaQ_owpOc3UjrTDxepnnk5N4_RYf4nWS5ldJmZU4y3GaVkXhtGO8TvPqCi9xvkyWRYULvHpfxMcpLL4qy6SsylVZZEWZr5LVIoaGGanu_J9r-oG9_wun3wo6');
    }
}
