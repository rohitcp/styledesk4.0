@extends('layouts.focused')

@section('title', __('clients.module.workspace.files.workflow.title').' — '.$client->displayName())

@section('content')
  {{--
      The upload workflow.

      The focused layout, like assigning a schedule: one task and no
      surrounding navigation, because a page offering the nav rail halfway
      through a set of treatment photographs is a page offering to abandon
      them.

      Everything below is one Vue island. The header, the body and the footer
      have to agree about whether there are unsaved changes — Back, Close and
      Cancel all ask the same question — and three separate pieces of markup
      cannot share that answer.

      The props are built above rather than written inline: a directive
      argument with a comma inside brackets does not parse, because Blade
      counts brackets rather than reading PHP.
  --}}
  @php
      $workflowProps = [
          'urls' => [
              'store' => route('clients.files.store', $client),
              'storeRecord' => route('clients.files.records.store', $client),
              'back' => route('clients.show', $client).'#files',
              'create' => route('clients.files.create', $client),
          ],
          'csrf' => csrf_token(),
          /* Which of the two, when the reader has already chosen. Null asks. */
          'kind' => $kind,
          /* What is being reopened, where anything is. */
          'draft' => $draft,
          'options' => $options,
          'limits' => $limits,
          'clientName' => $client->displayName(),
          'labels' => __('clients.module.workspace.files'),
      ];
  @endphp

  <div data-vue-component="ClientFileWorkflow" data-props='@json($workflowProps)'></div>
@endsection
