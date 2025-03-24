-   [ ] be able to setup a feature for each step in tasks or processes \*\* maybe
-   [ ] be able to validate inside a task

    ```php
        /**
         * Class ExampleTask
         *
         * @property-read string $name
         * @property-read string $email
         */
        class ExampleTask extends Task
        {
            protected function rules(): array
            {
                return [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:80']
                ];
            }

            public function handle():void
            {
                // ...
            }
        }
    ```
