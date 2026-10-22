@php
    $statusCode = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 400;
@endphp
@extends('errors.layout')

@section('code', (string) $statusCode)
@section('title', 'Request could not be completed')
@section('message', 'PRISM could not process this request. Check the page address or return to the previous screen.')
@section('tone', 'orange')
