<?php

declare(strict_types=1);

namespace venndev\vbossbar;

use Throwable;
use vennv\vapm\Async;
use vennv\vapm\FiberManager;
use vennv\vapm\Promise;
use vennv\vapm\System;

final class VAnimationBossBar
{

    private static array $promiseHandler = [];

    private static function exists(VBossBar $bossBar): bool
    {
        if (!isset(self::$promiseHandler[$bossBar->getId()])) {
            self::$promiseHandler[$bossBar->getId()] = true;
            return false;
        }
        return true;
    }

    private static function unset(VBossBar $bossBar): void
    {
        unset(self::$promiseHandler[$bossBar->getId()]);
    }

    /**
     * @throws Throwable
     */
    private static function sleep(int $time): Promise
    {
        return new Promise(function ($resolve) use ($time): void {
            System::setTimeout(function () use ($resolve) {
                $resolve();
            }, $time);
        });
    }

    /**
     * @param VBossBar $bossBar - The boss bar to animate
     * @param int $current - The current percentage
     * @param int $max - The maximum percentage
     * @param int $step - The step to increase the percentage
     * @param callable $callable (int currentPercent, VBossBar) - The callable to execute
     * @param int $speed - The speed to animate <milliseconds>
     * @return Async
     * @throws Throwable
     */
    public static function ascending(
        VBossBar $bossBar, int $current, int $max, int $step, callable $callable, int $speed = 0
    ): Async
    {
        return new Async(function () use ($bossBar, $current, $max, $step, $callable, $speed): void {
            if (!self::exists($bossBar)) {
                for ($i = $current; $i <= $max; $i += $step) {
                    $bossBar->setPercentage($i);
                    $callable($i, $bossBar);
                    Async::await(self::sleep($speed));
                }
                self::unset($bossBar);
            }
        });
    }

    /**
     * @param VBossBar $bossBar - The boss bar to animate
     * @param int $current - The current percentage
     * @param int $min - The minimum percentage
     * @param int $step - The step to decrease the percentage
     * @param callable $callable (int currentPercent, VBossBar) - The callable to execute
     * @param int $speed - The speed to animate <milliseconds>
     * @return Async
     * @throws Throwable
     */
    public static function descending(
        VBossBar $bossBar, int $current, int $min, int $step, callable $callable, int $speed = 0
    ): Async
    {
        return new Async(function () use ($bossBar, $current, $min, $step, $callable, $speed): void {
            if (!self::exists($bossBar)) {
                for ($i = $current; $i >= $min; $i -= $step) {
                    $bossBar->setPercentage($i);
                    $callable($i, $bossBar);
                    Async::await(self::sleep($speed));
                }
                self::unset($bossBar);
            }
        });
    }

    /**
     * @param VBossBar $bossBar - The boss bar to animate
     * @param int $current - The current percentage
     * @param int $max - The maximum percentage
     * @param int $step - The step to increase the percentage
     * @param callable $callable (int currentPercent, VBossBar) - The callable to execute
     * @param int $speed - The speed to animate <milliseconds>
     * @return Async
     * @throws Throwable
     */
    public static function pulse(VBossBar $bossBar, int $current, int $max, int $step, callable $callable, int $speed = 0): Async
    {
        return new Async(function () use ($bossBar, $current, $max, $step, $callable, $speed): void {
            if (!self::exists($bossBar)) {
                Async::await(self::ascending($bossBar, $current, $max, $step, $callable, $speed));
                Async::await(self::descending($bossBar, $max, $current, $step, $callable, $speed));
                self::unset($bossBar);
            }
        });
    }

    /**
     * @param VBossBar $bossBar - The boss bar to animate
     * @param array<int|string> $colors - The colors to change
     * @param callable $callable (int|string $color, VBossBar) - The callable to execute
     * @param int $speed - The speed to animate <milliseconds>
     * @throws Throwable
     *
     * This treatment makes the boss bar lose appearance a bit annoying for some people,
     *      if you have any suggestions, please support this!
     */
    public static function cycleColor(VBossBar $bossBar, array $colors, callable $callable, int $speed = 0): Async
    {
        return new Async(function () use ($bossBar, $colors, $callable, $speed): void {
            if (!self::exists($bossBar)) {
                foreach ($colors as $color) {
                    $players = $bossBar->getPlayers();
                    Async::await($bossBar->removePlayers($players));
                    $bossBar->setColor($color);
                    $bossBar->addPlayers($players);
                    $callable($color, $bossBar);
                    Async::await(self::sleep($speed));
                }
                self::unset($bossBar);
            }
        });
    }

    /**
     * @param VBossBar $bossBar - The boss bar to animate
     * @param array<int|string> $colors - The colors to change
     * @param callable $callable (int|string $color, VBossBar) - The callable to execute
     * @param int $speed - The speed to animate <milliseconds>
     * @throws Throwable
     */
    public static function cycleColorRandom(VBossBar $bossBar, array $colors, callable $callable, int $speed = 0): Async
    {
        return new Async(function () use ($bossBar, $colors, $callable, $speed): void {
            if (!self::exists($bossBar)) {
                foreach ($colors as $color) {
                    $players = $bossBar->getPlayers();
                    Async::await($bossBar->removePlayers($players));
                    $bossBar->setColor($colors[array_rand($colors)]);
                    $bossBar->addPlayers($players);
                    $callable($color, $bossBar);
                    Async::await(self::sleep($speed));
                }
                self::unset($bossBar);
            }
        });
    }

}