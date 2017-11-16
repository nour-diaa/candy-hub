<?php

namespace GetCandy\Console\Commands;

use Illuminate\Console\Command;
use GetCandy\Search\SearchContract;

class IndexAquaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'index:aqua';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $products = \GetCandy\Api\Products\Models\Product::with('variants')->get();
        $bar = $this->output->createProgressBar($products->count());

        foreach ($products as $product) {
            app(SearchContract::class)->indexObject($product);
            $bar->advance();
        }

        $bar->finish();
    }
}