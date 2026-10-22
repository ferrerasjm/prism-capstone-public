@php
    $statusCode = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500;
@endphp
@extends('errors.layout')

@section('code', (string) $statusCode)
@section('title', 'PRISM is temporarily unavailable')
@section('message', 'The system encountered an unexpected problem while processing your request.')
@section('tone', 'red')
