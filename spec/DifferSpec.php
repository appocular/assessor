<?php

namespace spec\Appocular\Assessor;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Appocular\Assessor\Differ;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use RuntimeException;

class DifferSpec extends ObjectBehavior
{
    function it_is_initializable(Client $client)
    {
        $this->beConstructedWith($client);
        $this->shouldHaveType(Differ::class);
    }

    function it_should_submit_diffs_to_keeper(Client $client, Response $response)
    {
        $response->getStatusCode()->willReturn(200);
        $client->post('diff', ['json' => ['image_kid' => 'image id', 'baseline_kid' => 'baseline id']])->willReturn($response)->shouldBeCalled();
        $this->beConstructedWith($client);
        $this->submit('image id', 'baseline id')->shouldReturn(null);
    }

    function it_should_deal_with_bad_response_codes(Client $client, Response $response)
    {
        $response->getStatusCode()->willReturn(300);
        $client->post('diff', ['json' => ['image_kid' => 'image id', 'baseline_kid' => 'baseline id']])->willReturn($response);

        $this->beConstructedWith($client);
        $this->shouldThrow(new RuntimeException('Bad response from Differ.'))->duringSubmit('image id', 'baseline id');
    }
}
