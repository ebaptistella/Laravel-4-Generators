<?php

namespace Way\Generators\Generators;

use Illuminate\Support\Pluralizer;

class ViewGenerator extends Generator {

    /**
     * Fetch the compiled template for a view
     *
     * @param  string $template Path to template
     * @param  string $name
     * @return string Compiled template
     */
    protected function getTemplate($template, $name)
    {
        $this->template = $this->file->get($template);

        if ($this->needsScaffolding($template))
        {
            return $this->getScaffoldedTemplate($name);
        }

        // Otherwise, just set the file
        // contents to the file name
        return $name;
    }

    /**
     * Get the scaffolded template for a view
     *
     * @param  string $name
     * @return string Compiled template
     */
    protected function getScaffoldedTemplate($name)
    {
        $model = $this->cache->getModelName();  // post
        $models = Pluralizer::plural($model);   // posts
        $Models = ucwords($models);             // Posts
        $Model = Pluralizer::singular($Models); // Post

        // Create and Edit views require form elements
        if ($name === 'create.blade' or $name === 'edit.blade')
        {
            $formElements = $this->makeFormElements();

            $this->template = str_replace('{{formElements}}', $formElements, $this->template);
        }

        // Replace template vars in view
        foreach(array('model', 'models', 'Models', 'Model') as $var)
        {
            $this->template = str_replace('{{'.$var.'}}', $$var, $this->template);
        }

        // And finally create the table rows
        list($headings, $fields, $editAndDeleteLinks) = $this->makeTableRows($model);
        $this->template = str_replace('{{headings}}', implode(PHP_EOL."\t\t\t\t\t\t\t\t\t\t", $headings), $this->template);
        $this->template = str_replace('{{fields}}', implode(PHP_EOL."\t\t\t\t\t\t\t\t\t\t\t", $fields) . PHP_EOL . $editAndDeleteLinks, $this->template);

        return $this->template;
    }

    /**
     * Create the table rows
     *
     * @param  string $model
     * @return Array
     */
    protected function makeTableRows($model)
    {
        $models = Pluralizer::plural($model); // posts

        $fields = $this->cache->getFields();

        // First, we build the table headings
        $headings = array_map(function($field) use ($model) {
            return '<th>{{ Lang::get("messages.form.'.$model.'.'.$field.'") }}</th>';
        }, array_keys($fields));
        
        // And then the rows, themselves
        $fields = array_map(function($field) use ($model) {
            return "<td>{{{ \$$model->$field }}}</td>";
        }, array_keys($fields));

        // Now, we'll add the edit and delete buttons.
        $editAndDelete = <<<EOT
                                            <td nowrap="nowrap" align="center" width="200">
                                                {{ Form::open(array('method' => 'DELETE', 'route' => array('admin.{$models}.destroy', \${$model}->id))) }}
                                                    <div class="btn-group">
                                                        <a href="{{ URL::to("admin/{$models}/{\${$model}->id}/edit/") }}" class="btn btn-info btn-sm" role="button"><span class="glyphicon glyphicon-edit"></span> {{ Lang::get('messages.templates.grid.edit') }}</a>
                                                        <button type="submit" class="btn btn-danger btn-sm"><span class="glyphicon glyphicon-trash"></span> {{ Lang::get('messages.templates.grid.delete') }}</button>
                                                    </div>
                                                {{ Form::close() }}
                                            </td>            
EOT;

        return array($headings, $fields, $editAndDelete);
    }

    /**
     * Add Laravel methods, as string,
     * for the fields
     *
     * @return string
     */
    public function makeFormElements()
    {
        $model = $this->cache->getModelName();
        $formMethods = array();

        foreach($this->cache->getFields() as $name => $type)
        {
            $formalName = ucwords($name);
            
            // TODO: add remaining types
            switch($type)
            {
                case 'integer':
                   $element = "{{ Form::input('number', '$name', Input::old('$name'), array('class' => 'form-control', 'placeholder' => '$formalName', 'id' => '$name')) }}";
                    break;

                case 'text':
                    $element = "{{ Form::textarea('$name', Input::old('$name'), array('class' => 'form-control', 'placeholder' => '$formalName', 'id' => '$name')) }}";
                    break;

                case 'boolean':
                    $element = "{{ Form::checkbox('$name', true, Input::old('$name')) }}";
                    break;

                default:
                    $element = "{{ Form::text('$name', Input::old('$name'), array('class' => 'form-control', 'placeholder' => '$formalName', 'id' => '$name')) }}";
                    break;
            }

            // Now that we have the correct $element,
            // We can build up the HTML fragment
            $frag = <<<EOT
            
            <div class="form-group @if (\$error = \$errors->first('$name')) has-error @endif">
                {{ Form::label('$name', Lang::get('messages.form.$model.$name').':', array('class' => 'col-lg-2 control-label')) }}
                <div class="col-lg-3 input-group">
                    <span class="input-group-addon glyphicon glyphicon-calendar"></span>
                    $element
                </div>
            </div>            
EOT;

            $formMethods[] = $frag;
        }

        return implode(PHP_EOL, $formMethods);
    }

}
