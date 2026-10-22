@extends('errors.layout')

@section('code', '429')
@section('title', 'Too many requests')
@section('message', 'PRISM received too many requests in a short period. Please wait briefly before trying again.')
@section('tone', 'orange')
@section('help', 'This temporary limit helps protect accounts and keeps the service available for everyone.')
