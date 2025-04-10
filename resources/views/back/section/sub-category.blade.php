<option value="">Seçim edin</option>
@foreach ($sub_categories as $sub_category)
    <option value="{{ $sub_category->id }}" {{ old('sub_category_id') == $sub_category->id ? 'selected' : '' }}>
        {{ $sub_category->name }}</option>
@endforeach
