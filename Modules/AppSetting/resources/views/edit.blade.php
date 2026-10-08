@php
    use Modules\AppSetting\Support\SettingField;
    $canEdit = auth()->user()->can('edit-appsetting');
@endphp
<x-layouts.admin :title="__('Settings')">
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('Sections') }}</h3>
                </div>
                <div class="card-body ">
                    <ul class="nav nav-pills flex-column">
                        @foreach ($groups as $group)
                            <li class="nav-item">
                                <a href="{{ route('admin.settings.edit', $group->key) }}" @class(['nav-link', 'active' => $group->key === $current->key])>
                                    <i class="{{ $group->icon }} mr-2" style="width: 1rem"></i> {{ __($group->label) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <form method="POST" action="{{ route('admin.settings.update', $current->key) }}" enctype="multipart/form-data" class="card card-primary card-outline">
                @csrf
                @method('PUT')
                <div class="card-header">
                    <h3 class="card-title"><i class="{{ $current->icon }} mr-1"></i> {{ __($current->label) }}</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">{{ __($current->description) }}</p>

                    <fieldset @disabled(! $canEdit)>
                        <div class="form-row">
                            @foreach ($current->fields as $field)
                                @php($value = old($field->key, $values[$field->key] ?? null))
                                @php($invalid = $errors->has($field->key))
                                <div class="form-group {{ $field->wide ? 'col-12' : 'col-md-6' }}">
                                    <label for="{{ $field->key }}">{{ __($field->label) }}</label>

                                    @switch($field->type)
                                        @case(SettingField::TEXTAREA)
                                            <textarea id="{{ $field->key }}" name="{{ $field->key }}" rows="3" @class(['form-control', 'is-invalid' => $invalid])>{{ $value }}</textarea>
                                            @break
                                        @case(SettingField::SELECT)
                                            <select id="{{ $field->key }}" name="{{ $field->key }}" @class(['custom-select', 'is-invalid' => $invalid])>
                                                @foreach ($field->options as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                            @break
                                        @case(SettingField::COLOR)
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <input type="color" value="{{ $value }}" class="form-control settings-color" data-sync="#{{ $field->key }}" id="{{ $field->key }}_picker">
                                                </div>
                                                <input id="{{ $field->key }}" name="{{ $field->key }}" value="{{ $value }}" maxlength="7" data-synced-by="#{{ $field->key }}_picker" @class(['form-control text-monospace', 'is-invalid' => $invalid])>
                                            </div>
                                            @break
                                        @case(SettingField::IMAGE)
                                            <div class="d-flex align-items-center">
                                                <div class="border rounded mr-3 d-flex align-items-center justify-content-center bg-light" style="width: 64px; height: 64px">
                                                    @if ($url = setting()->url($field->key))
                                                        <img src="{{ $url }}" alt="" style="max-width: 60px; max-height: 60px">
                                                    @else
                                                        <i class="far fa-image text-muted fa-2x"></i>
                                                    @endif
                                                </div>
                                                <div class="custom-file">
                                                    <input type="file" id="{{ $field->key }}" name="{{ $field->key }}" accept="image/*" data-placeholder="{{ __('Choose file') }}" @class(['custom-file-input', 'is-invalid' => $invalid])>
                                                    <label class="custom-file-label" for="{{ $field->key }}">{{ __('Choose file') }}</label>
                                                </div>
                                            </div>
                                            @break
                                        @default
                                            <input id="{{ $field->key }}" name="{{ $field->key }}" value="{{ $value }}"
                                                   type="{{ $field->type === SettingField::NUMBER ? 'number' : ($field->type === SettingField::EMAIL ? 'email' : 'text') }}"
                                                   @if ($field->type === SettingField::NUMBER) step="any" @endif
                                                   @class(['form-control', 'is-invalid' => $invalid])>
                                    @endswitch

                                    @if ($field->help) <small class="form-text text-muted">{{ __($field->help) }}</small> @endif
                                    @error($field->key) <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
                @if ($canEdit)
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary float-right"><i class="fas fa-save mr-1"></i>{{ __('Save :group', ['group' => strtolower(__($current->label))]) }}</button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-layouts.admin>
