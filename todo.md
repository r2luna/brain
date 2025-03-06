


- [ ] Service Provider
- [ ] Query Command
- [ ] Exception Command
- [ ] maybe change commands to brain:* instead of make:*
- [ ]

...
38|       - name: Get Composer cache directory
39|         id: composer-cache
40|         shell: bash
41|         run: |
42|           dir=$(composer config cache-files-dir)
43|           echo "dir=$dir" >> $GITHUB_OUTPUT
44|           echo "Cache directory: $dir"
45|
46|       - name: Cache dependencies
47|         uses: actions/cache@v3
48|         with:
49|           path: ${{ steps.composer-cache.outputs.dir }}
           key: dependencies-php-${{ matrix.php }}-os-${{ matrix.os }}-version-${{ matrix.dependency-version }}-composer-${{ hashFiles('composer.json') }}
51|           restore-keys: dependencies-php-${{ matrix.php }}-os-${{ matrix.os }}-version-${{ matrix.dependency-version }}-composer-
52|
53|       - name: Install Composer dependencies
54|         run: composer update --${{ matrix.dependency-version }} --no-interaction --prefer-dist
...
