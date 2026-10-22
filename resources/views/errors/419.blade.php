@extends('errors.layout')

@section('code', '419')
@section('title', 'Session expired')
@section('message', 'Your secure session or form token has expired. Sign in again before continuing.')
@section('primary_url', route('login'))
@section('primary_label', 'Sign In Again')
@section('tone', 'orange')
@section('help', 'For your security, PRISM may expire inactive sessions and forms that have been open for an extended period.')
