module.exports = function (api) {
  api.cache(true);

  return {
    presets: ['babel-preset-expo'],
    // Décorateurs de classe requis par les modèles WatermelonDB (@field,
    // @date, @children...) — voir src/db/models/Guest.ts.
    plugins: [['@babel/plugin-proposal-decorators', { legacy: true }]],
  };
};
