<?php

use Illuminate\Http\Client\Response;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

it('makes instance from http client response', function () {
    $response = $this->mock(Response::class, function ($mock) {
        $mock->shouldReceive('body')->once()->andReturn('Something happened.');
        $mock->shouldReceive('status')->once()->andReturn(500);
    });

    $instance = PlacesException::fromResponse($response);

    expect($instance)
        ->toBeInstanceOf(PlacesException::class)
        ->toBeInstanceOf(Exception::class)
        ->getMessage()->toBe('Places API Error occured. Response: Something happened.')
        ->getCode()->toBe(500);
});
